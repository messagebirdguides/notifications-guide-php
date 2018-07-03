<?php

// Create/open the database table
$db = new PDO('sqlite:orders.sqlite');

// Generate schema: 1 table
$db->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, name TEXT, phone TEXT, items TEXT, status TEXT);');

// Insert sample data
$stmt = $db->prepare('INSERT INTO orders (name, phone, items, status) VALUES (:name, :phone, :items, :status)');
$stmt->execute([
    'name' => 'Hannah Hungry',
    'phone' => '+319876543210', // <- put your number here for testing
    'items' => '1 x Hipster Burger + Fries',
    'status' => 'pending'
]);
$stmt->execute([
    'name' => 'Mike Madeater',
    'phone' => '+319876543211', // <- put your number here for testing
    'items' => '1 x Chef Special Mozzarella Pizza',
    'status' => 'pending'
]);