<?php
require __DIR__ . '/vendor/autoload.php';

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\GraphQL;

$pdo = new PDO('mysql:host=db;dbname=myapp;charset=utf8mb4', 'user', 'secret');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'id' => Type::nonNull(Type::id()),
        'fname' => Type::string(),
        'lname' => Type::string(),
        'description' => Type::string(),
    ],
]);

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'user' => [
            'type' => $userType,
            'args' => [
                'id' => Type::nonNull(Type::id()),
            ],
            'resolve' => function ($root, $args) use ($pdo) {
                $stmt = $pdo->prepare('SELECT id, fname, lname, description FROM users WHERE id = ?');
                $stmt->execute([$args['id']]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        ],
        'allUsers' => [
            'type' => Type::listOf($userType),
            'resolve' => function () use ($pdo) {
                $stmt = $pdo->query('SELECT id, fname, lname, description FROM users');
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]
    ]
]);

$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' => [
        'createUser' => [
            'type' => $userType,
            'args' => [
                'fname' => Type::nonNull(Type::string()),
                'lname' => Type::nonNull(Type::string()),
                'description' => Type::string(),
            ],
            'resolve' => function ($root, $args) use ($pdo) {
                $stmt = $pdo->prepare('INSERT INTO users (fname, lname, description) VALUES (?, ?, ?)');
                $stmt->execute([
                    $args['fname'],
                    $args['lname'],
                    $args['description'] ?? null,
                ]);
                $id = $pdo->lastInsertId();
                $stmt = $pdo->prepare('SELECT id, fname, lname, description FROM users WHERE id = ?');
                $stmt->execute([$id]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        ]
    ]
]);

$schema = new Schema([
    'query' => $queryType,
    'mutation' => $mutationType
]);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $query = $input['query'] ?? '';
    $result = GraphQL::executeQuery($schema, $query);
    $output = $result->toArray();
} catch (\Exception $e) {
    $output = ['errors' => [['message' => $e->getMessage()]]];
}

header('Content-Type: application/json');
echo json_encode($output);
