<?php
    class BookException extends Exception {}

    class Book {
        private $_id;
        private $_isbn;
        private $_name;
        private $_author;

        public function __construct($id, $isbn, $name, $author) {
            $this->setID($id);
            $this->setISBN($isbn);
            $this->setName($name);
            $this->setAuthor($author);
        }

        public function getID() {
            return $this->_id;
        }

        public function getISBN() {
            return $this->_isbn;
        }

        public function getName() {
            return $this->_name;
        }

        public function getAuthor() {
            return $this->_author;
        }

        public function setID($id) {
            if(($id !== null) && (!is_numeric($id) || $id <= 0 || $this->_id !== null)) {
                throw new BookException("Book ID error");
            }
            $this->_id = $id;
        }

        public function setISBN($isbn) {
            if(strlen($isbn) < 0 || strlen($isbn) > 13) {
                throw new BookException("Book isbn error");
            }
            $this->_isbn = $isbn;
        }

        public function setName($name) {
            if(strlen($name) < 0 || strlen($name) > 180) {
                throw new BookException("Book name error");
            }
            $this->_name = $name;
        }

        public function setAuthor($author) {
            if(strlen($author) < 0 || strlen($author) > 50) {
                throw new BookException("Book author error");
            }
            $this->_author = $author;
        }

        public function returnBookAsArray() {
            $book = array();
            $book['id'] = $this->getID();
            $book['isbn'] = $this->getISBN();
            $book['name'] = $this->getName();
            $book['author'] = $this->getAuthor();
            return $book;
        }
    }