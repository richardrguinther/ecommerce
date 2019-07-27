<?php

use \Hcode\PageAdmin;
use \Hcode\Model\User;
use \Hcode\Model\Order;
use \Hcode\Model\OrderStatus;

$app->get("/admin/orders/:idorder/status", function ($idorder) {
    User::verifyLogin();

    $order = new Order();

    $order->get((int) $idorder);

    $page = new PageAdmin();

    $page->setTpl("order-status", [
        "order" => $order->getValues(),
        "status" => OrderStatus::listAll(),
        "msgSuccess" => Order::getSuccess(),
        "msgError" => Order::getError()
    ]);
});

$app->post("/admin/orders/:idorder/status", function ($idorder) {
    User::verifyLogin();

    $order = new Order();

    $order->get((int) $idorder);

    if (!isset($_POST["idstatus"]) || !(int) $_POST["idstatus"] > 0) {
        Order::setError("Informe o status atual.");
        header("Location: /admin/orders/$idorder/status");
        exit;
    }

    $order->setidstatus((int) $_POST["idstatus"]);

    $order->save();

    Order::setSucess("Status atualizado.");
    header("Location: /admin/orders/$idorder/status");
    exit;
});

$app->get("/admin/orders/:idorder/delete", function ($idorder) {
    User::verifyLogin();

    $order = new Order();

    $order->get((int) $idorder);

    $order->delete();

    header("Location: /admin/orders");
    exit;
});

$app->get("/admin/orders/:idorder", function ($idorder) {
    User::verifyLogin();

    $order = new Order();

    $order->get((int) $idorder);

    $cart = $order->getCart();

    $page = new PageAdmin();

    $page->setTpl("order", [
        "order" => $order->getValues(),
        "cart" => $cart->getValues(),
        "products" => $cart->getProducts()
    ]);
});

$app->get("/admin/orders", function () {
    User::verifyLogin();

    $page = (isset($_GET["page"])) ? $_GET["page"] : 1;

    $search = (isset($_GET["search"])) ? $_GET["search"] : "";

    $orders = Order::makePagination($search, $page);

    // var_dump($orders);
    // exit;

    $page = new PageAdmin();

    if (count($orders) > 0) {
        $page->setTpl("orders", [
            "orders" => $orders[0]["data"],
            "pages" => $orders,
            "search" => $search
        ]);
    } else {
        $page->setTpl("orders", [
            "orders" => $orders,
            "pages" => $orders,
            "search" => $search
        ]);
    }
});
