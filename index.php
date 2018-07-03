<?php

require_once "vendor/autoload.php";

// Create app
$app = new Slim\App;

// Load configuration with dotenv
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

// Get container
$container = $app->getContainer();

// Register Twig component on container to use view templates
$container['view'] = function() {
    return new Slim\Views\Twig('views');
};

// Initialize database
$container['db'] = function() {
    return new PDO('sqlite:orders.sqlite');
};

// Load and initialize MesageBird SDK
$container['messagebird'] = function() {
    return new MessageBird\Client(getenv('MESSAGEBIRD_API_KEY'));
};

// Display page to list orders
$app->get('/', function($request, $response) {
    $orders = $this->db->query('SELECT * FROM orders');

    return $this->view->render($response, 'orders.html.twig',
        [ 'orders' => $orders ]);
});

// Execute action to update order
$app->post('/updateOrder', function($request, $response) {
    // Read request
    $id = $request->getParsedBodyParam('id');
    $newStatus = $request->getParsedBodyParam('status');

    // Get order
    $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = :id');
    $stmt->execute([ 'id' => $id ]);
    if ($stmt->columnCount() > 0) {
        $order = $stmt->fetch();

        // Update order
        $stmt = $this->db->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute([ 'id' => $id, 'status' => $newStatus ]);

        // Compose a message, based on status
        $body = "";
        switch ($newStatus) {
            case 'confirmed':
                $body = $order['name'] . ", thanks for ordering at BirdieNomNom Foods! We are now preparing your food with love and fresh ingredients and will keep you updated.";
                break;
            case 'delayed':
                $body = $order['name'] . ", sometimes good things take time! Unfortunately your order is slightly delayed but will be delivered as soon as possible.";
                break;
            case 'delivered':
                $body = $order['name'] . ", you can start setting the table! Our driver is on their way with your order! Bon appetit!";
                break;
        }

        // Create message object
        $message = new MessageBird\Objects\Message;
        $message->originator = 'BirdieNomNo';
        $message->recipients = [ $order['phone'] ];
        $message->body = $body;

        // Send the message through MessageBird's API
        try {
            $this->messagebird->messages->create($message);
            
            // Request was successful, return to previous view
            return $response->withRedirect('/', 301);

        } catch (Exception $e) {
            // Request has failed
            error_log($e->getMessage());
            return "Error occurred while sending message!";
        }

    } else {
        return "Invalid input!";
    }
});

// Start the application
$app->run();