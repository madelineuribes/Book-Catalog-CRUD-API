<?php

require_once('db.php');
require_once('../model/Book.php');
require_once('../model/Response.php');

// set up database connection
try {
    $connectDB = DB::connectDB();
} catch (PDOException $ex) {
    error_log("Connection error - " .$ex, 0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Database connection error");
    $response->send();
    exit;
}

// GET, DELETE, PATCH book by id
// check if bookid is in url - ex. /books/1
if(array_key_exists("bookid", $_GET)) {
    $bookid = $_GET['bookid']; 

    // Make sure ID is valid
    if($bookid == '' || !is_numeric($bookid)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Book ID cannot be blank or must be numeric");
        $response->send();
        exit;
    }

    // Get single book by id
    if($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {
            // creat db query
            $query = $connectDB->prepare('select id, isbn, name, author from books where id = :bookid');
            $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // creat book array to store returned book
            $booksArray = array();

            // check for unsuccessful return
            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Book not found");
                $response->send();
                exit;
            }

            // create book object, store in array to be returned in JSON data
            while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $book = new Book($row['id'], $row['isbn'], $row['name'], $row['author']);
                $booksArray[] = $book->returnBookAsArray();
            }

            // bundle books and rows into array
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['books'] = $booksArray;

            // successful return
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setData($returnData);
            $response->send();
            exit;

        } 
        // if error with sql query return a json error
        catch (BookException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("Database query error - " .$ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get book");
            $response->send();
            exit;
        }

    } 
    // Delete single book by id
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        try {
            // create db query
            $query = $connectDB->prepare('delete from books where id = :bookid');
            $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            // check for unsuccessful return
            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Book not found");
                $response->send();
                exit;
            }
            // Successful 
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("Book deleted");
            $response->send();
            exit;

        } 
        // if error with sql query return a json error
        catch (PDOException $ex) {
            error_log("Database query error - " .$ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to delete book");
            $response->send();
            exit;
        }
    } 
    // Update book by id
    elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        try {
            // check request's content type header is JSON
            if($_SERVER['CONTENT_TYPE'] !== 'application/json'){
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Content type header not set to JSON");
                $response->send();
                exit;
            }
             // get PATCH request body as the PATCHed data will be JSON format
            $rawPatchData = file_get_contents('php://input');

            if(!$jsonData = json_decode($rawPatchData)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Request body is not valid JSON");
                $response->send();
                exit;
            }
            // set book field updated to false initially
            $isbn_updated = false;
            $name_updated = false;
            $author_updated = false;

            // create blank query fields string to append each field to
            $queryFields = "";

            // check if isbn exists in PATCH
            if(isset($jsonData->isbn)) {
                $isbn_updated = true;
                $queryFields .= "isbn = :isbn, ";
            }

            if(isset($jsonData->name)) {
                $name_updated = true;
                $queryFields .= "name = :name, ";
            }
    
            if(isset($jsonData->author)) {
                $author_updated = true;
                $queryFields .= "author = :author, ";
            }

            // remove the right hand comma and trailing space
            $queryFields = rtrim($queryFields, ", ");

            // check if any book fields supplied in JSON
            if($isbn_updated === false && $name_updated === false && $author_updated === false) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("No book fields provided");
                $response->send();
                exit;
            }

             // create db query to get book from database to update
            $query = $connectDB->prepare('SELECT id, isbn, name, author from books where id = :bookid');
            $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            // make sure that the book exists for a given book id
            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No book found to update");
                $response->send();
                exit;
            }

             // for each row returned - should be just one
            while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // create new book object
                $book = new Book($row['id'], $row['isbn'], $row['name'], $row['author']);
            }

            // create and prepare the query string including any query fields
            $queryString = "update books set ".$queryFields." where id = :bookid";
            $query = $connectDB->prepare($queryString);

            if($isbn_updated === true) {
                $book->setISBN($jsonData->isbn);
                $up_isbn = $book->getISBN();
                $query->bindParam(':isbn', $up_isbn, PDO::PARAM_STR);
            }

            if($name_updated === true) {
                $book->setName($jsonData->name);
                $up_name = $book->getName();
                $query->bindParam(':name', $up_name, PDO::PARAM_STR);
            }

            if($author_updated === true) {
                $book->setAuthor($jsonData->author);
                $up_author = $book->getAuthor();
                $query->bindParam(':author', $up_author, PDO::PARAM_STR);
            }

            // bind the book id provided in the query string
            $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
            $query->execute();

            // check if row was actually updated, could be that the given values are the same as the stored values
            $rowCount = $query->rowCount();
            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Book not updated");
                $response->send();
                exit;
            }

             // create db query to return the newly edited book
            $query = $connectDB->prepare('select id, isbn, name, author from books where id = :bookid');
            $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            // check if book was found
            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No book found after update");
                $response->send();
                exit;
            }

            $booksArray = array();

            while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $book = new Book($row['id'], $row['isbn'], $row['name'], $row['author']);
                $booksArray[] = $book->returnBookAsArray();
            }

             // bundle books and rows returned into an array to return in the json data
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['books'] = $booksArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage("Book updated");
            $response->setData($returnData);
            $response->send();
            exit;

        } catch (BookException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("Database query error - " .$ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to update book");
            $response->send();
            exit;
        }
    } 
    // Error for other request methods
    else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
} 

// Get all books and Create new book
elseif (empty($_GET)) {

    // Get all books
    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            // create db query
            $query = $connectDB->prepare('select id, isbn, name, author from books');
            $query->execute();

            $rowCount = $query->rowCount();

            // book array to store returned books
            $booksArray = array();

            // create book object for each row returned
            while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $book = new Book($row['id'], $row['isbn'], $row['name'], $row['author']);
                $booksArray[] = $book->returnBookAsArray();
            }

            // bundle books and rows into array for JSON data
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['books'] = $booksArray;

            // successful return response
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setData($returnData);
            $response->send();
            exit;

        } 
        // if error with sql query return a json error
        catch (BookException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } catch (PDOException $ex) {
            error_log("Database query error - " .$ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to get books");
            $response->send();
            exit;
        }

    } 
    // Create new book
    elseif($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Make sure content type is JSON
            if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Content type header is not set to JSON");
                $response->send();
                exit;
            }
            // get POST request body as the POSTed data will be JSON format
            $rawPostData = file_get_contents('php://input');

            // Decode JSON from rawPOST - checking for false
            if(!$jsonData = json_decode($rawPostData)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Request body is not valid JSON");
                $response->send();
                exit;
            }

            // Make sure all values are included
            if(!isset($jsonData->isbn) || !isset($jsonData->name) || !isset($jsonData->author)) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                (!isset($jsonData->isbn) ? $response->addMessage("ISBN must be provided") : false);
                (!isset($jsonData->name) ? $response->addMessage("Name must be provided") : false);
                (!isset($jsonData->author) ? $response->addMessage("Author must be provided") : false);
                $response->send();
                exit;
            }

            // create new book with data
            $newBook = new Book(null, $jsonData->isbn, $jsonData->name, $jsonData->author);

            // store variables
            $isbn = $newBook->getISBN();
            $name = $newBook->getName();
            $author = $newBook->getAuthor();

            // create db query
            $query = $connectDB->prepare('insert into books (isbn, name, author) values (:isbn, :name, :author)');
            $query->bindParam(':isbn', $isbn, PDO::PARAM_STR);
            $query->bindParam(':name', $name, PDO::PARAM_STR);
            $query->bindParam(':author', $author, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            // check if row was actually inserted
            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to create book");
                $response->send();
                exit;
            }

            // get last book id so we can return the Book in the json
            $lastBookID = $connectDB->lastInsertId();

            // Retrieve newly created book
            $query = $connectDB->prepare('SELECT id, isbn, name, author from books where id = :bookid');
            $query->bindParam(':bookid', $lastBookID, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            // make sure that the new book was returned
            if($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to retrieve book after creation");
                $response->send();
                exit;
            }

            // create empty array to store books
            $booksArray = array();
            
            // create new book for each row
            while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $book = new Book($row['id'], $row['isbn'], $row['name'], $row['author']);
                $booksArray[] = $book->returnBookAsArray();
            }

            // bundle books and rows returned into array for JSON
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['books'] = $booksArray;

            // successful return
            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage("Book created");
            $response->setData($returnData);
            $response->send();
            exit;

        } 
        // if book fails to create due to data types, missing fields or invalid data then send error json
        catch (BookException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit;
        } 
        // if error with sql query return a json error
        catch (PDOException $ex) {
            error_log("Database query error - " .$ex, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to insert book into database");
            $response->send();
            exit;
        }

    } 
    // if any other request method apart from GET or POST is used then return 405 method not allowed
    else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }

} 
// return 404 error if endpoint not available
else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("Endpoint not found");
    $response->send();
    exit;
}