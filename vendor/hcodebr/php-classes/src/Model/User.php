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
    const SUCCESS = "UserSucesss";

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
			!(int)$_SESSION[User::SESSION]["iduser"] > 0
		) {
			//Não está logado
			return false;
		} else {
			if ($inadmin === true && (bool)$_SESSION[User::SESSION]['inadmin'] === true) {
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

        $results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :LOGIN", array(
            ":LOGIN" => $login
        ));

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

        $results = $sql->select("SELECT * FROM tb_users as a INNER JOIN tb_persons as b USING(idperson) WHERE a.iduser = :iduser", array(
            ":iduser" => $iduser
        ));

        $data = $results[0];

        $data["desperson"] = utf8_encode($data["desperson"]);

        $this->setData($data[0]);
    }

    public function update()
    {
        $sql = new Sql();
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

    public static function getForgot($email)
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

                $link = "http://www.hcodecommerce.com.br/admin/forgot/reset?code=$code";

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
        INNER JOIN tb_users as b USING(iduser) 
        INNER JOIN tb_persons as c USING(idperson) 
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
        return password_hash($password, PASSWORD_DEFAULT,[
            "cost" => 12
        ]);
    }

    public function checkLoginExists($login)
    {
        $query = "SELECT * FROM tb_users WHERE deslogin = :deslogin";

        $sql = new Sql();

        $results = $sql->select($query,[
            ":deslogin" => $login
        ]);

        return(count($results) > 0);
    }
}