<?php

class Database {
    private $host;
    private $port;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    private $pdo;

    public function __construct($config) {
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->dbname = $config['dbname'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->charset = $config['charset'];
    }

    public function connect() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }
        return $this->pdo;
    }
}