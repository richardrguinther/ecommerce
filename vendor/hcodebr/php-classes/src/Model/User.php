<?php

namespace Hcode\Model;

use \Hcode\Model;
use \Hcode\DB\Sql;
use \Hcode\Mailer;

class User extends Model
{
    const SESSION = "User";
    const SECRET = "HcodePhp7_Secret";
    const SECRET_IV = "HcodePhp7_Secret_IV";
    const ERROR = "UserError";
    const ERROR_REGISTER = "UserErrorRegister";
    const SUCCESS = "UserSucess";

    public static function getFromSession()
    {
        if (isset($_SESSION[User::SESSION]) && (int) $_SESSION[User::SESSION]["iduser"] > 0) {
            $user = new User();

            $user->setData($_SESSION[User::SESSION]);
        }

        return $user;
    }

    public static function checkLogin($inadmin = true)
    {
        if (
            !isset($_SESSION[User::SESSION])
            ||
            !$_SESSION[User::SESSION]
            ||
            !(int) $_SESSION[User::SESSION]["iduser"] > 0
        ) {
            //Não está logado
            return false;
        } else {
            if ($inadmin === true && (bool) $_SESSION[User::SESSION]['inadmin'] === true) {
                return true;
            } else if ($inadmin === false) {
                return true;
            } else {
                return false;
            }
        }
    }

    public static function login($login, $password)
    {
        $sql = new Sql();

        // $results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :LOGIN", array(
        //     ":LOGIN" => $login
        // ));

        $query = "SELECT * 
        FROM tb_users as a
        INNER JOIN tb_persons as b 
        ON a.idperson = b.idperson
        WHERE deslogin = :LOGIN;";

        $results = $sql->select($query, [
            ":LOGIN" => $login
        ]);

        if (count($results) === 0) {
            throw new \Exception("Usuário inexistente ou senha inválida.");
        }

        $data = $results[0];

        if (password_verify($password, $data["despassword"]) === true) {
            $user = new User();

            $data["deslogin"] = utf8_decode($data["deslogin"]);

            $user->setData($data);

            $_SESSION[User::SESSION] = $user->getValues();

            return $user;
        } else {
            throw new \Exception("Usuário inexistente ou senha inválida.");
        }
    }

    public static function verifyLogin($inadmin = true)
    {
        if (!User::checkLogin($inadmin) === true) {
            if ($inadmin) {
                header("Location: /admin/login");
            } else {
                header("Location: /login");
            }
            exit;
        }
    }

    public static function logout()
    {
        $_SESSION[User::SESSION] = NULL;
    }

    public static function listAll()
    {
        $sql = new Sql();

        return $sql->select("SELECT * FROM tb_users as a INNER JOIN tb_persons as b USING(idperson) ORDER BY b.desperson");
    }

    public function save()
    {
        $sql = new Sql();

        $results = $sql->select("CALL sp_users_save(:desperson, :deslogin,:despassword,:desemail, :nrphone, :inadmin)", array(
            ":desperson" => utf8_decode($this->getdesperson()),
            ":deslogin" => $this->getdeslogin(),
            ":despassword" => User::getPasswordHash($this->getdespassword()),
            ":desemail" => $this->getdesemail(),
            ":nrphone" => $this->getnrphone(),
            ":inadmin" => $this->getinadmin()
        ));

        $this->setData($results[0]);
    }

    public function get($iduser)
    {
        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users as a INNER JOIN tb_persons as b ON a.idperson = b.idperson WHERE a.iduser = :iduser;", array(
            ":iduser" => $iduser
        ));

        $data = $results[0];

        $data["desperson"] = utf8_encode($data["desperson"]);

        $this->setData($data);
    }

    public function update()
    {
        $sql = new Sql();

        //$query = 'CALL sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)';

        $results = $sql->select("CALL sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
            ":iduser" => $this->getiduser(),
            ":desperson" => utf8_decode($this->getdesperson()),
            ":deslogin" => $this->getdeslogin(),
            ":despassword" => User::getPasswordHash($this->getdespassword()),
            ":desemail" => $this->getdesemail(),
            ":nrphone" => $this->getnrphone(),
            ":inadmin" => $this->getinadmin()
        ));
        $this->setData($results[0]);
    }

    public function delete()
    {
        $sql = new Sql();
        $sql->query("CALL sp_users_delete(:iduser)", array(
            ":iduser" => $this->getiduser()
        ));
    }

    public static function getForgot($email, $inadmin = true)
    {
        $sql = new Sql();

        $query = "SELECT * FROM tb_persons as a INNER JOIN tb_users as b USING(idperson) WHERE a.desemail = :email";

        $results = $sql->select($query, array(
            ":email" => $email
        ));

        if (count($results) === 0) {
            throw new \Exception("Não foi possível recuperar a senha");
        } else {
            $data = $results[0];

            $results2 = $sql->select("CALL sp_userspasswordsrecoveries_create(:iduser, :desip)", array(
                ":iduser" => $data["iduser"],
                ":desip" => $_SERVER["REMOTE_ADDR"]
            ));

            if (count($results2) === 0) {
                throw new Exception("Não foi possível recuperar a senha.");
            } else {
                $dataRecovery = $results2[0];

                $cipher = "AES-128-CBC";

                $code1 = openssl_encrypt(
                    $dataRecovery["idrecovery"],
                    $cipher,
                    pack("a16", User::SECRET),
                    0,
                    pack("a16", User::SECRET_IV)
                );

                $code = base64_encode($code1);

                if ($inadmin === true) {
                    $link = "http://www.hcodecommerce.com.br/admin/forgot/reset?code=$code";
                } else {
                    $link = "http://www.hcodecommerce.com.br/forgot/reset?code=$code";
                }

                $mailer = new Mailer($data["desemail"], $data["desperson"], "Redefinir a Senha", "forgot", array(
                    "name" => $data["desperson"],
                    "link" => $link
                ));

                $mailer->send();

                return $link;
            }
        }
    }

    public static function validForgotDecrypt($code)
    {
        $data = base64_decode($code);
        $cipher = "AES-128-CBC";

        $idrecovery = openssl_decrypt(
            $data,
            $cipher,
            pack("a16", User::SECRET),
            0,
            pack("a16", User::SECRET_IV)
        );

        $query = "SELECT * FROM tb_userspasswordsrecoveries as a 
        INNER JOIN tb_users as b ON a.iduser = b.iduser
        INNER JOIN tb_persons as c ON b.idperson = c.idperson 
        WHERE a.idrecovery = :idrecovery
        AND a.dtrecovery IS NULL 
        AND DATE_ADD(a.dtregister, INTERVAL 1 HOUR) >= NOW();";

        $sql = new Sql();

        $results = $sql->select($query, array(
            ":idrecovery" => $idrecovery
        ));

        if (count($results) === 0) {
            throw new \Exception("Não foi possível recuperar a senha.");
        } else {
            return $results[0];
        }
    }

    public static function setForgotUsed($idrecovery)
    {
        $sql = new Sql();

        $sql->query("UPDATE tb_userspasswordsrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery", array(
            ":idrecovery" => $idrecovery
        ));
    }

    public function setPassword($password)
    {
        $sql = new Sql();

        $sql->query("UPDATE tb_users SET despassword = :password WHERE iduser = :iduser", array(
            ":password" => $password,
            ":iduser" => $this->getiduser()
        ));
    }

    public static function setSuccess($msg)
    {
        $_SESSION[User::SUCCESS] = $msg;
    }

    public static function getSuccess()
    {
        $msg = (isset($_SESSION[User::SUCCESS])) ? $_SESSION[User::SUCCESS] : "";
        User::clearSuccess();
        return $msg;
    }

    public static function clearSuccess()
    {
        $_SESSION[User::SUCCESS] = NULL;
    }

    public static function setError($msg)
    {
        $_SESSION[User::ERROR] = $msg;
    }

    public static function getError()
    {
        $msg = (isset($_SESSION[User::ERROR])) ? $_SESSION[User::ERROR] : "";
        User::clearError();
        return $msg;
    }

    public static function clearError()
    {
        $_SESSION[User::ERROR] = NULL;
    }


    public static function setErrorRegister($msg)
    {
        $_SESSION[User::ERROR_REGISTER] = $msg;
    }

    public static function getErrorRegister()
    {
        $msg = (isset($_SESSION[User::ERROR_REGISTER])) ? $_SESSION[User::ERROR_REGISTER] : "";
        User::clearErrorRegister();
        return $msg;
    }

    public static function clearErrorRegister()
    {
        $_SESSION[User::ERROR_REGISTER] = NULL;
    }

    public static function getPasswordHash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT, [
            "cost" => 12
        ]);
    }

    public function checkLoginExists($login)
    {
        $query = "SELECT * FROM tb_users WHERE deslogin = :deslogin";

        $sql = new Sql();

        $results = $sql->select($query, [
            ":deslogin" => $login
        ]);

        return (count($results) > 0);
    }

    public function getOrders()
    {
        $sql = new Sql();

        $query = "SELECT * 
            FROM tb_orders AS a 
            INNER JOIN tb_ordersstatus AS b 
            ON a.idstatus = b.idstatus
            INNER JOIN tb_carts AS c 
            ON c.idcart = a.idcart
            INNER JOIN tb_users AS d 
            ON d.iduser = a.iduser
            INNER JOIN tb_addresses AS e
            ON a.idaddress = e.idaddress
            INNER JOIN tb_persons AS f
            ON f.idperson = d.idperson
            WHERE a.iduser = :iduser
            ";

        $results = $sql->select($query, [
            ":iduser" => $this->getiduser()
        ]);

        if (count($results) > 0) {
            return $results;
        }
    }
}
