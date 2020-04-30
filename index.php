<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/DB.php';
require __DIR__ . '/Controllers/AuthController.php';
require __DIR__ . '/Controllers/ProfileController.php';

$app = AppFactory::create();

/*$app->add(function (ServerRequestInterface $request, RequestHandler $handler) {
	return $handler->handle($request);
});*/

$app->post('/register', function (Request $request, Response $response, $args) {

	$data = $request->getParsedBody();

	list($user_id, $token) = AuthController::register($data);

   	$response->getBody()->write(json_encode(compact('user_id','token')));
   	return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/login', function (Request $request, Response $response, $args) {

	$data = $request->getParsedBody();

	$resp = AuthController::login($data);

	$response->getBody()->write(json_encode($resp));
   	return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/profile', function (Request $request, Response $response, $args) {

	$data = $request->getParsedBody();

	$profile = new ProfileController($data);

	$resp = $profile->run('view');

	$response->getBody()->write(json_encode($resp));
   	return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
