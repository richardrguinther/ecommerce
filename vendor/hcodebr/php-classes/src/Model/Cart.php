<?php

namespace Hcode\Model;

use \Hcode\Model;
use \Hcode\DB\Sql;
use \Hcode\Model\Product;
use \Hcode\Model\User;

class Cart extends Model
{
    const SESSION = "Cart";
    const SESSION_ERROR = "CartError";

    public static function getFromSession()
    {
        $cart = new Cart();

        if (isset($_SESSION[Cart::SESSION]) && (int) $_SESSION[Cart::SESSION]["idcart"] > 0) {
            $cart->get((int) $_SESSION[Cart::SESSION]["idcart"]);
        } else {
            $cart->getFromSessionID();

            if (!(int) $cart->getidcart() > 0) {
                $data = [
                    "dessessionid" => session_id(),
                ];

                if (User::checkLogin(false)) {
                    $user = User::getFromSession();
                    $data["iduser"] = $user->getiduser();
                }

                $cart->setData($data);

                $cart->save();

                $cart->setToSession();
            }
        }

        return $cart;
    }

    public function setToSession()
    {
        $_SESSION[Cart::SESSION] = $this->getValues();
    }

    public function getFromSessionID()
    {
        $sql = new Sql();

        $query = "SELECT * FROM tb_carts WHERE idcart = :idcart";

        $results = $sql->select($query, ["idcart" => session_id()]);

        if (count($results) > 0) {
            $this->setData($results[0]);
        }
    }

    public function get(int $idcart)
    {
        $sql = new Sql();

        $query = "SELECT * FROM tb_carts WHERE idcart = :idcart";

        $results = $sql->select($query, ["idcart" => $idcart]);

        if (count($results) > 0) {
            $this->setData($results[0]);
        }
    }

    public function save()
    {
        $sql = new Sql();

        $results = $sql->select("CALL sp_carts_save(:idcart,:dessessionid,:iduser,:deszipcode, :vlfreight, :nrdays)", [
            ":idcart" => $this->getidcart(),
            ":dessessionid" => $this->getdessessionid(),
            ":iduser" => $this->getiduser(),
            ":deszipcode" => $this->getdeszipcode(),
            ":vlfreight" => $this->getvlfreight(),
            ":nrdays" => $this->getnrdays()
        ]);

        $this->setData($results[0]);
    }

    public function addProduct(Product $product)
    {
        $query = "INSERT INTO tb_cartsproducts(idcart,idproduct) VALUES(:idcart,:idproduct)";

        $sql = new Sql();
        $sql->select($query, array(
            ":idcart" => $this->getidcart(),
            ":idproduct" => $product->getidproduct()
        ));

        $this->getCalculateTotal();
    }

    public function removeProduct(Product $product, $all = false)
    {

        $sql = new Sql();

        if ($all === true) {
            $query = 'UPDATE tb_cartsproducts SET dtremoved = NOW()WHERE idcart = :idcart AND idproduct = :idproduct AND dtremoved IS NULL';

            $sql->query($query, [
                ":idcart" => $this->getidcart(),
                ":idproduct" => $product->getidproduct()
            ]);
        } else {
            $query = 'UPDATE tb_cartsproducts SET dtremoved = NOW()WHERE idcart = :idcart AND idproduct = :idproduct AND dtremoved IS NULL LIMIT 1';

            $sql->query($query, [
                ":idcart" => $this->getidcart(),
                ":idproduct" => $product->getidproduct()
            ]);
        }

        $this->getCalculateTotal();
    }

    public function getProducts()
    {
        $sql = new Sql();

        $query = "SELECT b.idproduct, b.desproduct, b.vlprice, b.vlwidth, b.vlheight, b.vllength, b.vlweight, b.desurl,
        COUNT(*) as nrqtd,
        SUM(b.vlprice) AS vltotal
        FROM tb_cartsproducts as a 
        INNER JOIN tb_products as b 
        ON a.idproduct = b.idproduct 
        WHERE a.idcart = :idcart 
        AND a.dtremoved IS NULL 
        GROUP BY b.idproduct, b.desproduct, b.vlprice, b.vlwidth, b.vlheight, b.vllength, b.vlweight, b.desurl
        ORDER BY b.desproduct";

        $rows = $sql->select($query, array(
            ":idcart" => $this->getidcart()
        ));

        return Product::checkList($rows);
    }

    public function getProductsTotals()
    {
        $query = "SELECT 
        SUM(vlprice) AS vlprice, 
        SUM(vlwidth) AS vlwidth, 
        SUM(vlheight) AS vlheight,
        SUM(vllength) AS vllength,
        SUM(vlweight) AS vlweight,
        COUNT(*) AS nrqtd
        FROM tb_products AS a
        INNER JOIN tb_cartsproducts AS b
        ON a.idproduct = b.idproduct 
        WHERE b.idcart = :idcart AND dtremoved IS NULL;";

        $sql = new Sql();

        $results = $sql->select($query, [":idcart" => $this->getidcart()]);

        if (count($results) > 0) {
            return $results[0];
        } else {
            return [];
        }
    }

    public function setFreight($nrzipcode)
    {
        $nrzipcode = str_replace("-", "", $nrzipcode);

        $totals = $this->getProductsTotals();

        if ($totals["nrqtd"] > 0) {

            if ($totals["vlweight"] < 1) $totals["vlweight"] = 1;
            if ($totals["vllength"] < 16) $totals["vllength"] = 16;

            $qs = http_build_query([
                "nCdEmpresa" => "",
                "sDsSenha" => "",
                "nCdServico" => "04014",
                "sCepOrigem" => "09853120",
                "sCepDestino" => $nrzipcode,
                "nVlPeso" => $totals["vlweight"],
                "nCdFormato" => "1",
                "nVlComprimento" => $totals["vllength"],
                "nVlAltura" => $totals["vlheight"],
                "nVlLargura" => $totals["vlwidth"],
                "nVlDiametro" => "0",
                "sCdMaoPropria" => "s",
                "nVlValorDeclarado" => $totals["vlprice"],
                "sCdAvisoRecebimento" => "n"
            ]);


            $xml = simplexml_load_file("http://ws.correios.com.br//calculador/CalcPrecoPrazo.asmx/CalcPrecoPrazo?" . $qs);

            $result = $xml->Servicos->cServico;

            if ($result->MsgErro != "") {
                Cart::setMsgError($result->MsgErro);
            } else {
                Cart::clearMsgError();
            }

            $this->setnrdays($result->PrazoEntrega);
            $this->setvlfreight(Cart::formatValueToDecimal($result->Valor));
            $this->setdeszipcode($nrzipcode);

            $this->save();

            return $result;
        } else {
            throw new \Exception("Houve um erro ao consultar o frete.");
        }
    }

    public static function formatValueToDecimal($value): float
    {
        $value = str_replace(".", "", $value);
        return str_replace(",", ".", $value);
    }

    public static function setMsgError($msg)
    {
        $_SESSION[Cart::SESSION_ERROR] = $msg;
    }

    public static function getMsgError()
    {
        $msg = (isset($_SESSION[Cart::SESSION_ERROR])) ? $_SESSION[Cart::SESSION_ERROR] : "";

        Cart::clearMsgError();

        return $msg;
    }

    public static function clearMsgError()
    {
        $_SESSION[Cart::SESSION_ERROR] = NULL;
    }

    public function updateFreight()
    {
        if ($this->getdeszipcode() != '') {
            $this->setFreight($this->getdeszipcode());
        }
    }

    public function getValues()
    {
        $this->getCalculateTotal();

        return parent::getValues();
    }

    public function getCalculateTotal()
    {
        $this->updateFreight();

        $totals = $this->getProductsTotals();

        $this->setvlsubtotal($totals["vlprice"]);
        $this->setvltotal($totals["vlprice"] + $this->getvlfreight());
    }
}
