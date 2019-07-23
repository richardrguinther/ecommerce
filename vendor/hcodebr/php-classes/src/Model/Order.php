<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;

class Order extends Model
{
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
}
