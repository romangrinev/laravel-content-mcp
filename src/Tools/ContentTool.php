<?php

namespace GrinevStudio\LaravelContentMcp\Tools;

use Closure;
use GrinevStudio\LaravelContentMcp\ContentManager;
use GrinevStudio\LaravelContentMcp\Exceptions\ContentMcpException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Throwable;

abstract class ContentTool extends Tool
{
    public function __construct(protected readonly ContentManager $manager) {}

    protected function execute(Request $request, Closure $callback): ResponseFactory
    {
        try {
            return Response::structured($callback($request));
        } catch (ContentMcpException $exception) {
            $result = ['error' => $exception->errorCode];
            if ($exception->recoveryHint !== null) {
                $result['recovery_hint'] = $exception->recoveryHint;
            }
            if (is_string($request->get('correlation_id'))) {
                $result['correlation_id'] = $request->get('correlation_id');
            }

            return Response::make(Response::error(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)))->withStructuredContent($result);
        } catch (Throwable) {
            $result = ['error' => 'unexpected_error'];

            return Response::make(Response::error(json_encode($result)))->withStructuredContent($result);
        }
    }
}
