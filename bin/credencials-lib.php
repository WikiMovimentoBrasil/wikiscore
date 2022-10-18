<?php

/*
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
LICENSE
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

Copyright by Code Boxx

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.


=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
MORE
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
Please visit https://code-boxx.com/ for more!
*/

class credencials {
  // (A) CONSTRUCTOR - CONNECT DATABASE
  private $pdo = null;
  private $stmt = null;
  public $error = null;
  function __construct () {
    try {
      $this->pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,
        DB_USER, DB_PASSWORD, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
      );
    } catch (Exception $ex) { exit($ex->getMessage()); }
  }

  // (B) DESTRUCTOR - CLOSE CONNECTION
  function __destruct () {
    if ($this->stmt !== null) { $this->stmt = null; }
    if ($this->pdo !== null) { $this->pdo = null; }
  }

  // (C) GET USER BY EMAIL
  function getByEmail ($email) {
    $this->stmt = $this->pdo->prepare("SELECT * FROM `{$contest['name_id']}__credencials` WHERE `user_email`=?");
    $this->stmt->execute([$email]);
    return $this->stmt->fetch();
  }

  // (D) VERIFY EMAIL PASSWORD
  // SESSION MUST BE STARTED!
  function login ($email, $password) {
    // (D1) ALREADY SIGNED IN
    if (isset($_SESSION['user'])) { return true; }
    
    // (D2) GET USER
    $user = $this->getByEmail($email);
    if (!is_array($user)) { return false; }

    // (D3) USER STATUS
    if ($user['user_status']!="A") { return false; }

    // (D4) VERIFY PASSWORD + REGISTER SESSION
    if (password_verify($password, $user['user_password'])) {
      $_SESSION['user'] = [];
      foreach ($user as $k=>$v) {
        if ($k!="user_password") { $_SESSION['user'][$k] = $v; }
      }
      $_SESSION['user']['contest'] = CONTEST;
      return true;
    }
    return false;
  }

  // (E) SAVE USER
  function save ($email, $pass, $id=null) {
    $name = strstr($email, "@", true);
    if ($name == false) return false;
    $name = trim($name, "@");
    
    if ($id===null) {
      $sql = "INSERT INTO `{$contest['name_id']}__credencials` (`user_name`, `user_email`, `user_password`) VALUES (?,?,?)";
      $data = [$name, $email, password_hash($pass, PASSWORD_DEFAULT)];
    } else {
      $sql = "UPDATE `{$contest['name_id']}__credencials` SET `user_name`=?, `user_email`=?, `user_password`=? WHERE `user_id`=?";
      $data = [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $id];
    }
    try {
      $this->stmt = $this->pdo->prepare($sql);
      $this->stmt->execute($data);
      return true;
    } catch (Exception $ex) {
      $this->error = $ex->getMessage();
      return false;
    }
  }
}

// (F) DATABASE SETTINGS - CHANGE TO YOUR OWN!
require "data.php";
define('DB_HOST', $db_host);
define('DB_NAME', $database);
define('DB_CHARSET', 'utf8');
define('DB_USER', $db_user);
define('DB_PASSWORD', $db_pass);
define('CONTEST', $contest['name_id']);

// (G) CREATE USER OBJECT
$USR = new credencials();