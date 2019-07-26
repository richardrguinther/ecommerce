<?php

namespace Hcode\Model;

use \Hcode\Model;
use \Hcode\DB\Sql;

class Product extends Model
{
    public static function listAll()
    {
        $sql = new Sql();

        return $sql->select("SELECT * FROM tb_products ORDER BY desproduct");
    }

    public static function checkList($list)
    {
        foreach ($list as &$row) {
            $p = new Product();

            $p->setData($row);
            $row = $p->getValues();
        }

        return $list;
    }

    public function save()
    {
        $sql = new Sql();

        $values = array(
            ":idproduct" => $this->getidproduct(),
            ":desproduct" => $this->getdesproduct(),
            ":vlprice" => $this->getvlprice(),
            ":vlwidth" => $this->getvlwidth(),
            ":vlheight" => $this->getvlheight(),
            ":vllength" => $this->getvllength(),
            ":vlweight" => $this->getvlweight(),
            ":desurl" => $this->getdesurl()
        );

        $results = $sql->select("CALL sp_products_save(:idproduct,:desproduct, :vlprice, :vlwidth, :vlheight, :vllength, :vlweight, :desurl)", $values);

        $this->setData($results[0]);

        Category::updateFile();
    }

    public function get($idproduct)
    {
        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_products WHERE idproduct = :idproduct", array(
            ":idproduct" => $idproduct
        ));

        $this->setData($results[0]);
    }

    public function delete()
    {
        $sql = new Sql();

        $sql->query("DELETE FROM tb_products WHERE idproduct = :idproduct", array(
            ":idproduct" => $this->getidproduct()
        ));
    }

    public function setPhoto($file)
    {

        $extension = explode(".", $file["name"]);
        $extension = end($extension);

        switch ($extension) {
            case "jpg":
            case "jpeg":
                $image = imagecreatefromjpeg($file["tmp_name"]);
                break;

            case "gif":
                $image = imagecreatefromgif($file["tmp_name"]);
                break;

            case "png":
                $image = imagecreatefrompng($file["tmp_name"]);
                break;
        }

        $dist = $_SERVER["DOCUMENT_ROOT"] . DIRECTORY_SEPARATOR .
            "res" . DIRECTORY_SEPARATOR .
            "site" . DIRECTORY_SEPARATOR .
            "img" . DIRECTORY_SEPARATOR .
            "products" . DIRECTORY_SEPARATOR .
            $this->getidproduct() . ".jpg";

        imagejpeg($image, $dist);
        imagedestroy($image);

        $this->checkPhoto();
    }

    public function checkPhoto()
    {
        if (file_exists(
            $_SERVER["DOCUMENT_ROOT"] . DIRECTORY_SEPARATOR .
                "res" . DIRECTORY_SEPARATOR .
                "site" . DIRECTORY_SEPARATOR .
                "img" . DIRECTORY_SEPARATOR .
                "products" . DIRECTORY_SEPARATOR .
                $this->getidproduct() . ".jpg"
        )) {
            $url = "/res/site/img/products/" . $this->getidproduct() . ".jpg";
        } else {
            $url = "/res/site/img/product.jpg";
        }

        $this->setdesphoto($url);
    }

    public function getValues()
    {
        $this->checkPhoto();

        $values = parent::getValues();

        return $values;
    }

    public function getFromURL($desurl)
    {
        $sql = new Sql();

        $query = "SELECT * FROM tb_products WHERE desurl = :desurl";

        $rows = $sql->select($query, array(
            ":desurl" => $desurl
        ));

        $this->setData($rows[0]);
    }

    public function getCategories()
    {
        $sql = new Sql();

        $query = "SELECT * FROM tb_categories as a INNER JOIN tb_productscategories as b ON a.idcategory = b.idcategory WHERE b.idproduct = :idproduct";

        return $sql->select($query, [
            ":idproduct" => $this->getidproduct()
        ]);
    }

    public static function getPages($page, $limitPerPage = 10)
    {
        $start = (($page - 1) * $limitPerPage);

        $sql = new Sql();

        $query =    "SELECT SQL_CALC_FOUND_ROWS *
                    FROM tb_products
                    ORDER BY desproduct
                    LIMIT $start, $limitPerPage;
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
                    FROM tb_products
                    WHERE desproduct LIKE :search
                    ORDER BY desproduct
                    LIMIT $start, $limitPerPage
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
            $pagination = Product::getPages($page);
        } else {
            $pagination = Product::getPagesSearch($search, $page);
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
