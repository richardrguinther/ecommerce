<?php

use \Hcode\PageAdmin;
use \Hcode\Model\User;
use \Hcode\Model\Product;

$app->get("/admin/products", function () {
    User::verifyLogin();

    $page = (isset($_GET["page"])) ? $_GET["page"] : 1;

    $search = (isset($_GET["search"])) ? $_GET["search"] : "";

    $products = Product::makePagination();

    $page = new PageAdmin();

    $page->setTpl("products", [
        "products" => $products[0]["data"],
        "search" => $search,
        "pages" => $products
    ]);
});

$app->get("/admin/products/create", function () {
    User::verifyLogin();

    $page = new PageAdmin();

    $page->setTpl("products-create");
});

$app->get("/admin/products/:idproduct", function ($idproduct) {
    User::verifyLogin();

    $product = new Product();

    $page = new PageAdmin();

    $product->get((int) $idproduct);

    $page->setTpl("products-update", array(
        "product" => $product->getValues()
    ));
});

$app->get("/admin/products/:idproduct/delete", function ($idproduct) {

    User::verifyLogin();

    $product = new Product();

    $product->get((int) $idproduct);

    $product->delete();

    header("Location: /admin/products");
    exit;
});

$app->post("/admin/products/create", function () {
    User::verifyLogin();

    $product = new Product();

    $product->setData($_POST);

    $product->save();

    header("Location:/admin/products");
    exit;
});

$app->post("/admin/products/:idproduct", function ($idproduct) {
    User::verifyLogin();

    $product = new Product();

    $product->get((int) $idproduct);

    $product->setData($_POST);

    $product->save();

    $product->setPhoto($_FILES["file"]);

    header("Location: /admin/products");
    exit;
});
