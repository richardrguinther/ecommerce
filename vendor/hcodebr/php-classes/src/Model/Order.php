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

    public static function getPages($page, $limitPerPage = 10)
    {
        $start = (($page - 1) * $limitPerPage);

        $sql = new Sql();

        $query =    "SELECT SQL_CALC_FOUND_ROWS *
                    FROM tb_orders AS a
                    INNER JOIN tb_carts AS b
                    ON a.idcart = b.idcart
                    INNER JOIN tb_users AS c
                    ON a.iduser = c.iduser
                    INNER JOIN tb_persons AS d
                    ON d.idperson = c.idperson
                    INNER JOIN tb_ordersstatus AS e
                    ON a.idstatus = e.idstatus
                    ORDER BY idorder DESC
                    LIMIT $start,$limitPerPage;
                    ";

        $queryTotal = "SELECT FOUND_ROWS() as ntotal";

        $results = $sql->select($query);

        $totalResults = $sql->select($queryTotal);

        return array(
            "data" => $results,
            "total" => (int) $totalResults[0]["ntotal"],
            "pages" => ceil($totalResults[0]["ntotal"] / $limitPerPage)
        );
    }

    public static function getPagesSearch($search, $page, $limitPerPage = 10)
    {
        $sql = new Sql();

        $start = (($page - 1) * $limitPerPage);

        $query =    "SELECT SQL_CALC_FOUND_ROWS *
                    FROM tb_orders AS a
                    INNER JOIN tb_carts AS b
                    ON a.idcart = b.idcart
                    INNER JOIN tb_users AS c
                    ON a.iduser = c.iduser
                    INNER JOIN tb_persons AS d
                    ON d.idperson = c.idperson
                    INNER JOIN tb_ordersstatus AS e
                    ON a.idstatus = e.idstatus
                    WHERE desperson LIKE :search
                    OR deslogin LIKE :search
                    OR desemail LIKE :search
                    ORDER BY idorder DESC
                    LIMIT $start,$limitPerPage;
                    ";

        $queryTotal = "SELECT FOUND_ROWS() AS ntotal";

        $results = $sql->select($query, [
            ":search" => "%$search%"
        ]);

        $totalResults = $sql->select($queryTotal);

        return array(
            "data" => $results,
            "total" => (int) $totalResults[0]["ntotal"],
            "pages" => ceil($totalResults[0]["ntotal"] / $limitPerPage)
        );
    }

    public static function makePagination()
    {

        $page = (isset($_GET["page"])) ? $_GET["page"] : 1;

        $search = (isset($_GET["search"])) ? $_GET["search"] : "";

        if ($search === "" || $search === null) {
            $pagination = Order::getPages($page);
        } else {
            $pagination = Order::getPagesSearch($search, $page);
        }

        $data = $pagination["data"];

        $pages = [];

        for ($x = 0; $x < $pagination["pages"]; $x++) {
            array_push($pages, array(
                "href" => "/admin/products?" . http_build_query([
                    "page" => $x + 1,
                    "search" => $search
                ]),
                "text" => $x + 1,
                "data" => $data
            ));
        }

        return $pages;
    }
}
