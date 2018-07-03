# Order Notifications

⏱ 15 min build time

Have you ever ordered food for home delivery on the phone, via the web or using an app? You might have found yourself wondering whether your order was received correctly and how long it takes until it is delivered!

For on-demand industries such as food delivery, ridesharing and logistics, excellent customer service during the ordering process is essential. One easy way to stand out from the crowd is proactive communication that keeps customers in the loop about the current status of their orders. Whether your customer is waiting for a package delivery or growing "hangry" (i.e. Hungry + Angry) awaiting their food delivery, sending timely ETA notifications via SMS is a great strategy to create a seamless user experience. The main benefits are that it's instant and works with almost every phone, independently of where the order was taken. Plus, it's already common to collect phone numbers as part of the order so it's easy to get customers on board with this.

Our SMS Messaging API provides an easy way to fully automate and integrate the process into order handling software. Your busy employees can trigger the notification with the push of a single button. Hangriness circumvented and user experience boosted, just like that!

## Getting Started

This guide will demonstrate a running application in PHP. The application is a prototype for an order management system deployed by a fictitious food delivery company called Birdie NomNom Foods.

Birdie NomNom Foods has implemented the following workflow:

- New incoming orders are in a _pending_ state.
- Once the kitchen starts preparing an order, it moves to the _confirmed_ state. A message is sent to the customer to inform them about this.
- When the food is ready and handed over to the delivery driver, staff marks the order _delivered._ A message is sent to the customer to let them know it will arrive momentarily.
- **Optional:** If preparation takes longer than expected, it can be moved to a _delayed_ state. A message is sent to the customer asking them to hang on just a little while longer. Thanks to this, Birdie NomNom Foods saves time spent answering _"Where's my order?"_ calls.

To run the sample application, you need to have PHP installed on your machine. If you're using a Mac, PHP is already installed. For Windows, you can [get it from windows.php.net](https://windows.php.net/download/). Linux users, please check your system's default package manager. You also need Composer, which is available from [getcomposer.org](https://getcomposer.org/download/), to install the [MessageBird SDK for PHP](https://github.com/messagebird/php-rest-api) and other dependencies.

Download the sample application [by cloning the GitHub repository](https://github.com/messagebirdguides/notifications-guide-php) or retrieving and extracting the ZIP file.

Then, open a console pointed at the directory into which you've stored the sample application and run the following command:

````bash
composer install
````

Apart from the MessageBird SDK, Composer will install the [Slim framework](https://packagist.org/packages/slim/slim), the [Twig templating engine](https://packagist.org/packages/slim/twig-view), and the [Dotenv configuration library](https://packagist.org/packages/vlucas/phpdotenv). These libraries add some structure to the project while keeping the sample application straightforward to understand without the overhead of a full-scale web framework.

## Configuring the MessageBird SDK

The SDK is defined as a dependency in `composer.json`:

````json
{
    "require" : {
        "messagebird/php-rest-api" : "^1.9.4"
        ...
    }
}
````

The SDK is available to the application through Composer autoloading and can be initialized by creating an instance of the `MessageBird\Client` class. The constructor takes a single argument, an API key. For our Slim-based application, we add the SDK on the dependency injection container:

````php
// Load and initialize MesageBird SDK
$container['messagebird'] = function() {
    return new MessageBird\Client(getenv('MESSAGEBIRD_API_KEY'));
};
````

Using `getenv()` we load the API key from an environment variable as it's a bad practice to keep credentials in the source code. To make the key available in the environment variable we need to initialize Dotenv and then add the key to a `.env` file. You can copy the `env.example` file provided in the repository to `.env` and then add your API key like this:

````env
MESSAGEBIRD_API_KEY=YOUR-API-KEY
````

You can create or retrieve an API key from the [API access (REST) tab](https://dashboard.messagebird.com/en/developers/access) in the _Developers_ section of your MessageBird account.

## Creating a Data Model and Sample Data

Our sample application uses a relational database to store the customer details and status of orders. It is configured to use a single-file [SQLite](https://www.sqlite.org/) database, which is natively supported by PHP through PDO so that it works out of the box without the need to configure an external RDBMS like MySQL.

The schema is a single table named _orders_ with the fields _id_, _name_, _phone_, _items_ and _status_.

The file `init.php` in the repository contains the code to set up the database table and insert two rows of sample data. Open this file with your text editor or IDE and update the queries; you should replace the sample data with a phone number you own for at least one of the rows marked like this:

````php
    'phone' => '+319876543210', // <- put your number here for testing
````

After updating the file, save it and run the following command:

````bash
php init.php
````

Note that this command only works once. If you make changes and want to recreate the database, you must delete the file `orders.sqlite` that the script creates before rerunning it:

````bash
rm orders.sqlite
php init.php
````

## Listing Orders

The application contains a view to list orders and let the staff trigger notifications. You can browse the HTML for this admin interface in `views/orders.html.twig` and the implementation in `index.php` that renders this view is this:

````php
// Display page to list orders
$app->get('/', function($request, $response) {
    $orders = $this->db->query('SELECT * FROM orders');

    return $this->view->render($response, 'orders.html.twig',
        [ 'orders' => $orders ]);
});
````

## Notifying Customer by Triggering an SMS

The sample application triggers SMS delivery in the `/updateOrder` route after updating the stored data. The definition of the route starts like this:

````php
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
````

Updating the stored data is done with an SQL UPDATE query:

````php
        // Update order
        $stmt = $this->db->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute([ 'id' => $id, 'status' => $newStatus ]);
````

Depending on the value of `$newStatus`, we formulate a different notification text and store it in the `$body` variable:

````php
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
````

Sending a message with the MessageBird SDK is straightforward: call the `messages->create()` method with a `MessageBird\Objects\Message` object. This  object needs at least the following attributes:
- `originator`: A sender ID for the SMS, either a telephone number (including country code) or an alphanumeric string with at most 11 characters.
- `recipients`: One or more phone numbers to send the message to. In our example, we use a single phone number and take it from the order database.
- `body`: The content of the message. For this application, we use the `$body` generated in the previous step.

Check out [the API documentation](https://developers.messagebird.com/docs/messaging#messaging-send) for optional parameters.

Here's how to create the `Message` object in PHP:

````php
        // Create message object
        $message = new MessageBird\Objects\Message;
        $message->originator = 'BirdieNomNo';
        $message->recipients = [ $order['phone'] ];
        $message->body = $body;
````

Now we can send the prepared object. As the MessageBird SDK throws exceptions for any error, the next section is surrounded by a try-catch block. Using `$this->messagebird` we access the previously initialized SDK object and then call the `messages->create()` method. If everything was OK, i.e., the API accepted our request, we redirect the user back to the initial page. If there's an error, we catch the exception and log the message. Here is the code:

````php
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
````

## Testing the Application

You can test the application with PHP's built-in web server. Before launching the application, though, check again whether you have already updated and run the database initialization script as described above in the "Creating a Data Model and Sample Data" section. Then, enter the following command on the console to start:

````bash
php -S 0.0.0.0:8080 index.php
````

Next, point your browser at http://localhost:8080/ to see the list of orders.

Click on one of the buttons in the _Action_ column to trigger a status change and, at the same time, automatically send a message. Awesome!

## Nice work!

You now have a running SMS Notifications application!

You can now use the flow, code snippets and UI examples from this tutorial as an inspiration to build your own notification system.
