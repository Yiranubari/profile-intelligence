<?php

namespace App\Middleware;

use App\Exceptions\DuplicateProfileException;
use App\Exceptions\ExternalApiException;
use App\Exceptions\ProfileNotFoundException;
use App\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Psr7\Response;
use Throwable;

class ErrorHandlerMiddleware implements MiddlewareInterface
{
    use JsonResponseTrait;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (DuplicateProfileException $e) {
            return $this->json(new Response(), 200, [
                'status'  => 'success',
                'message' => $e->getMessage(),
                'data'    => $e->getProfile(),
            ]);
        } catch (ProfileNotFoundException $e) {
            return $this->json(new Response(), 404, [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ]);
        } catch (ValidationException $e) {
            return $this->json(new Response(), $e->getStatusCode(), [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ]);
        } catch (ExternalApiException $e) {
            return $this->json(new Response(), 502, [
                'status'  => '502',
                'message' => $e->getMessage(),
            ]);
        } catch (HttpNotFoundException $e) {
            return $this->json(new Response(), 404, [
                'status'  => 'error',
                'message' => 'Endpoint not found',
            ]);
        } catch (HttpMethodNotAllowedException $e) {
            return $this->json(new Response(), 405, [
                'status'  => 'error',
                'message' => 'Method not allowed for this endpoint',
            ]);
        } catch (Throwable $e) {
            // Uncomment the next line during local development to see actual 500 errors in the logs
            // error_log($e->getMessage()); 

            return $this->json(new Response(), 500, [
                'status'  => 'error',
                'message' => 'Internal server error',
            ]);
        }
    }
}
