<?php

use \Hcode\PageAdmin;
use \Hcode\Model\User;

$app->get("/admin", function () {

    User::verifyLogin();

    $page = new PageAdmin();

    $page->setTpl("index");
});

$app->get("/admin/login", function () {
    if (isset($_SESSION[User::SESSION])) header("Location: /admin");

    $page = new PageAdmin([
        "header" => false,
        "footer" => false
    ]);

    $page->setTpl("login", [
        "error" => User::getError()
    ]);
});

$app->post("/admin/login", function () {

    if (!isset($_POST["login"]) || $_POST["login"] === "") {
        User::setError("Preencha o login.");
        header("Location: /admin/login");
        exit;
    }

    if (!isset($_POST["senha"]) || $_POST["senha"] === "") {
        User::setError("Preencha o campo da senha.");
        header("Location: /admin/login");
        exit;
    }

    User::login($_POST["login"], $_POST["senha"]);
    header("Location: /admin");
    exit;
});

$app->get("/admin/logout", function () {
    User::logout();

    header("Location: /admin/login");
    exit;
});
