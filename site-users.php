<?php

use Hcode\Page;
use Hcode\Model\User;

$app->get("/login", function () {

    $page = new Page();

    $page->setTpl("login", array(
        "error" => User::getError(),
        "errorRegister" => User::getErrorRegister(),
        "registerValues" => (isset($_SESSION["registerValues"])) ? $_SESSION['registerValues'] : [
            'name' => "",
            'email' => "",
            'phone' => ""
        ]
    ));
});

$app->post("/login", function () {

    try {
        User::login($_POST["login"], $_POST["password"]);
    } catch (\Exception $e) {
        User::setError($e->getMessage());
    }

    header("Location: /checkout");
    exit;
});

$app->get("/logout", function () {
    User::logout();

    header("Location: /login");
    exit;
});

$app->post("/register", function () {

    $_SESSION["registerValues"] = $_POST;

    if (!isset($_POST["name"]) || $_POST["name"] == "") {
        User::setErrorRegister("Preencha o seu nome.");
        header("Location: /login");
        exit;
    }

    if (!isset($_POST["email"]) || $_POST["email"] == "") {
        User::setErrorRegister("Preencha seu email.");
        header("Location: /login");
        exit;
    }

    if (!isset($_POST["password"]) || $_POST["password"] == "") {
        User::setErrorRegister("Preencha sua senha.");
        header("Location: /login");
        exit;
    }

    $user = new User();

    if ($user->checkLoginExists($_POST["email"]) === true) {
        User::setErrorRegister("Este endereço de e-mail já foi usado.");
        header("Location: /login");
        exit;
    }

    $user->setData(array(
        "inadmin" => 0,
        "deslogin" => $_POST["email"],
        "desperson" => $_POST["name"],
        "desemail" => $_POST["email"],
        "despassword" => $_POST["password"],
        "phone" => $_POST["phone"]
    ));


    $user->save();

    try {
        User::login($_POST["email"], $_POST["password"]);
    } catch (\Exception $e) {
        User::setErrorRegister($e->getMessage());
        header("Location: /login");
    }
    header("Location: /checkout");
    exit;
});

$app->get("/forgot", function () {
    $page = new Page();

    $page->setTpl("forgot");
});

$app->post("/forgot", function () {

    $user = User::getForgot($_POST["email"], false);

    header("Location: /forgot/sent");
    exit;
});

$app->get("/forgot/sent", function () {
    $page = new Page();

    $page->setTpl("forgot-sent");
});

$app->get("/forgot/reset", function () {
    $user = User::validForgotDecrypt($_GET["code"]);

    $page = new Page();

    $page->setTpl("forgot-reset", array(
        "name" => $user["desperson"],
        "code" => $_GET["code"]
    ));
});

$app->post("/forgot/reset", function () {
    $forgot = User::validForgotDecrypt($_POST["code"]);

    User::setForgotUsed($forgot["idrecovery"]);

    $user = new User();

    $user->get((int) $forgot["iduser"]);

    $password = password_hash($_POST["password"], PASSWORD_DEFAULT, [
        "cost" => 12
    ]);

    $user->setPassword($password);

    $page = new Page();

    $page->setTpl("forgot-reset-success");
});

$app->get("/profile", function () {
    User::verifyLogin(false);

    $user = User::getFromSession();

    $page = new Page();

    $page->setTpl("profile", [
        "user" => $user->getValues(),
        "profileMsg" => User::getSuccess(),
        "profileError" => User::getError()
    ]);
});

$app->post("/profile", function () {
    $user = User::getFromSession();

    User::verifyLogin(false);

    if (!isset($_POST["desperson"]) || $_POST["desperson"] === "") {
        User::setError("Preencha o seu nome.");
        header("Location: /profile");
        exit;
    }

    if (!isset($_POST["desemail"]) || $_POST["desemail"] === "") {
        User::setError("Preencha o seu email.");
        header("Location: /profile");
        exit;
    }

    if ($_POST["desemail"] !== $user->getdesemail()) {
        if (User::checkLoginExists($_POST["desemail"])) {
            User::setError("Este e-mail já está sendo usado.");
            header("Location: /profile");
            exit;
        }
    }


    $_POST["inadmin"] = $user->getinadmin();
    $_POST["despassword"] = $user->getdespassword();
    $_POST["deslogin"] = $_POST["desemail"];

    $user->setData($_POST);

    $user->save();

    User::setSuccess("Dados alterados com sucesso.");

    header("Location: /profile");
    exit;
});
