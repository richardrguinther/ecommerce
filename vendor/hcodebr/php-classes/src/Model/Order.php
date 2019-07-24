<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Model\Cart;

class Order extends Model
{
    const SUCCESS = "Order-Success";
    const ERROR = "Order-Error";


    public function save()
    {
        $sql = new Sql();

        $query = "CALL sp_orders_save(:idorder, :idcart, :iduser, :idstatus, :idaddress, :vltotal)";

        $results = $sql->select($query, [
            ':idorder' => $this->getidorder(),
            ':idcart' => $this->getidcart(),
            ':iduser' => $this->getiduser(),
            ':idstatus' => $this->getidstatus(),
            ':idaddress' => $this->getidaddress(),
            ':vltotal' => $this->getvltotal()
        ]);

        if (!$results) {
            throw new \Exception("Erro ao processar o pedido.");
        }

        if (count($results) > 0) {
            $this->setData($results[0]);
        }
    }

    public function get($idorder)
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
        WHERE a.idorder = :idorder
        ";

        $results = $sql->select($query, [
            ":idorder" => $idorder
        ]);

        if (count($results) > 0) {
            $this->setData($results[0]);
        }
    }

    public function listAll()
    {
        $sql = new Sql();

        $query = "SELECT *
        FROM tb_orders a 
        INNER JOIN tb_ordersstatus b
        ON a.idstatus = b.idstatus
        INNER JOIN tb_carts c
        ON c.idcart = a.idcart
        INNER JOIN tb_users d
        ON d.iduser = a.iduser 
        INNER JOIN tb_persons f 
        ON f.idperson = d.idperson
        ORDER BY a.dtregister DESC
        ";

        $results = $sql->select($query);

        return $results;
    }

    public function delete()
    {
        $sql = new Sql();

        $query = "DELETE FROM tb_orders WHERE idorder = :idorder";

        $sql->query($query, [
            ":idorder" => $this->getidorder()
        ]);
    }

    public function getCart(): Cart
    {
        $cart = new Cart();

        $cart->get((int) $this->getidcart());

        return $cart;
    }

    public static function getSuccess()
    {
        $msg = (isset($_SESSION[Order::SUCCESS])) ? $_SESSION[Order::SUCCESS] : "";
        Order::clearSuccess();
        return $msg;
    }

    public static function setSucess($msg)
    {
        $_SESSION[Order::SUCCESS] = $msg;
    }

    public static function clearSuccess()
    {
        $_SESSION[Order::SUCCESS] = NULL;
    }

    public static function setError($msg)
    {
        $_SESSION[Order::ERROR] = $msg;
    }

    public static function getError()
    {
        $msg = (isset($_SESSION[Order::ERROR])) ? $_SESSION[Order::ERROR] : "";
        Order::clearError();
        return $msg;
    }

    public static function clearError()
    {
        $_SESSION[Order::ERROR] = NULL;
    }
}
