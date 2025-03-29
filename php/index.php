<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/vendor/autoload.php';

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\GraphQL;

// DB-anslutning
$pdo = new PDO('mysql:host=db;dbname=myapp;charset=utf8mb4', 'user', 'secret');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Typ: User
$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'id' => Type::nonNull(Type::id()),
        'fname' => Type::string(),
        'lname' => Type::string(),
        'description' => Type::string(),
    ],
]);

// Typ: Adress
$adressType = new ObjectType([
    'name' => 'Adress',
    'fields' => [
        'id' => Type::nonNull(Type::id()),
        'street' => Type::string(),
        'postcode' => Type::string(),
        'city' => Type::string(),
    ],
]);

// Queries
$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'user' => [
            'type' => $userType,
            'args' => ['id' => Type::nonNull(Type::id())],
            'resolve' => function ($root, $args) use ($pdo) {
                $stmt = $pdo->prepare('SELECT id, fname, lname, description FROM users WHERE id = ?');
                $stmt->execute([$args['id']]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            },
        ],
        'allUsers' => [
            'type' => Type::listOf($userType),
            'resolve' => function () use ($pdo) {
                $stmt = $pdo->query('SELECT id, fname, lname, description FROM users');
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            },
        ],
        'allAdresses' => [
    'type' => Type::listOf($adressType),
    'resolve' => function () use ($pdo) {
        try {
            $stmt = $pdo->query('SELECT id, street, postcode, city FROM adress');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            file_put_contents('debug_adress_error.txt', $e->getMessage());
            return null;
        }
    },
],
    ],
]);

// Mutationer
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
            },
        ],
        'createAdress' => [
            'type' => $adressType,
            'args' => [
                'street' => Type::nonNull(Type::string()),
                'postcode' => Type::nonNull(Type::string()),
                'city' => Type::nonNull(Type::string()),
            ],
            'resolve' => function ($root, $args) use ($pdo) {
                $stmt = $pdo->prepare('INSERT INTO adress (street, postcode, city) VALUES (?, ?, ?)');
                $stmt->execute([
                    $args['street'],
                    $args['postcode'],
                    $args['city'],
                ]);
                $id = $pdo->lastInsertId();
                $stmt = $pdo->prepare('SELECT id, street, postcode, city FROM adress WHERE id = ?');
                $stmt->execute([$id]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            },
        ],
    ],
]);

// Visa GraphiQL i webbläsaren
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>GraphiQL</title>
        <link href="https://cdn.jsdelivr.net/npm/graphiql@2.0.11/graphiql.min.css" rel="stylesheet" />
    </head>
    <body style="margin: 0;">
    <div id="graphiql" style="height: 100vh;"></div>
    <script src="https://cdn.jsdelivr.net/npm/react@17/umd/react.production.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/react-dom@17/umd/react-dom.production.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/graphiql@2.0.11/graphiql.min.js"></script>
    <script>
      const graphQLFetcher = graphQLParams =>
        fetch(window.location.origin, {
          method: "post",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(graphQLParams),
        }).then(response => response.json());

      ReactDOM.render(
        React.createElement(GraphiQL, { fetcher: graphQLFetcher }),
        document.getElementById("graphiql"),
      );
    </script>
    </body>
    </html>
    <?php
    exit;
}

// Kör GraphQL vid POST
try {
    $input = json_decode(file_get_contents('php://input'), true);
    $query = $input['query'] ?? '';
    $variableValues = $input['variables'] ?? null;

    $schema = new Schema([
        'query' => $queryType,
        'mutation' => $mutationType,
    ]);

    $result = GraphQL::executeQuery($schema, $query, null, null, $variableValues);
    $output = $result->toArray();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'errors' => [['message' => $e->getMessage(), 'stack' => $e->getTraceAsString()]]
    ]);
    exit;
}

header('Content-Type: application/json');
echo json_encode($output);
