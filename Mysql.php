<?php
class Mysql {
    private $host = 'localhost'; // Change if your database server is not local
    private $username = 'root'; // Replace with your database username
    private $password = ''; // Replace with your database password
    private $database = 'project1'; // Your database name
    public $conn;

    // Constructor to initialize the connection
    public function __construct() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);

        // Check connection
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    // Method to close the connection
    public function close() {
        $this->conn->close();
    }

    // You can add other database methods here (e.g., for queries, updates, etc.)
}

// Create a new instance of the Mysql class
$c = new Mysql();
$conn = $c->conn;

?>
