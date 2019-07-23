<?php

use Hcode\Page;
use Hcode\Model\Product;
use Hcode\Model\Category;
use Rain\Tpl\Exception;

require_once("site-carts.php");
require_once("site-payments.php");
require_once("site-users.php");

$app->get('/', function () {

    $products = Product::listAll();

    $page = new Page();

    $page->setTpl("index", [
        "products" => Product::checkList($products)
    ]);
});

$app->get("/categories/:idcategory", function ($idcategory) {
    $p = (isset($_GET["page"])) ? (int) $_GET["page"] : 1;

    $category = new Category();

    $category->get((int) $idcategory);

    $pagination = $category->getProductsPage($p);

    $pages = [];

    for ($i = 1; $i <= $pagination["pages"]; $i++) {
        array_push($pages, array(
            "link" => '/categories/' . $category->getidcategory() . '?page=' . $i,
            "page" => $i
        ));
    }

    $page = new Page();

    $page->setTpl("category", array(
        "category" => $category->getValues(),
        "products" => $pagination["data"],
        "pages" => $pages
    ));
});

$app->get("/products/:desurl", function ($desurl) {
    $product = new Product();

    $product->getFromURL($desurl);

    $page = new Page();

    $page->setTpl("product-detail", [
        "product" => $product->getValues(),
        "categories" => $product->getCategories()
    ]);
});