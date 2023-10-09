<?php

namespace Ledc\Mark;

use Closure;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Ledc\Mark\Http\Request;
use Ledc\Mark\Http\Response;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;
use Workerman\Worker;
use function FastRoute\simpleDispatcher;

/**
 * 微型API框架
 */
class App extends Worker
{
    /**
     * Worker实例
     * @var Worker|null
     */
    protected static ?Worker $worker = null;
    /**
     * 路由
     * @var array
     */
    protected array $routeInfo = [];
    /**
     * 路由调度器
     * @var Dispatcher|null
     */
    protected ?Dispatcher $dispatcher = null;
    /**
     * 路由分组前缀
     * @var string
     */
    protected string $pathPrefix = '';

    /**
     * {@inheritdoc}
     */
    public function run(): void
    {
        $this->onWorkerStart = array($this, 'onWorkerStart');
        $this->onWorkerReload = array($this, 'onWorkerReload');
        $this->onWorkerStop = array($this, 'onWorkerStop');
        parent::run();
    }

    /**
     * 当进程启动时一些初始化工作
     * @param Worker $worker
     * @return void
     */
    protected function onWorkerStart(Worker $worker): void
    {
        static::$worker = $worker;
        Http::requestClass(Request::class);
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        $this->dispatcher = simpleDispatcher(function (RouteCollector $r) {
            foreach ($this->routeInfo as $method => $callbacks) {
                foreach ($callbacks as $info) {
                    $r->addRoute($method, $info[0], $info[1]);
                }
            }
        });

        $this->onMessage = [$this, 'onMessage'];
    }

    /**
     * 进程热重载回调
     * @return void
     */
    protected function onWorkerReload(): void
    {
    }

    /**
     * 当进程关闭时一些清理工作
     * @return void
     */
    protected function onWorkerStop(): void
    {
    }

    /**
     * @param TcpConnection $connection
     * @param Request $request
     * @return void
     */
    public function onMessage(TcpConnection $connection, Request $request): void
    {
        static $callbacks = [];
        try {
            Context::set(Request::class, $request);
            $path = $request->path();
            $method = $request->method();
            $key = $method . $path;
            $callback = $callbacks[$key] ?? null;
            if ($callback) {
                $connection->send($callback($request));
                return;
            }

            $ret = $this->dispatcher->dispatch($method, $path);
            if ($ret[0] === Dispatcher::FOUND) {
                $callback = $ret[1];
                if (!empty($ret[2])) {
                    $args = array_values($ret[2]);
                    $callback = function ($request) use ($args, $callback) {
                        return $callback($request, ... $args);
                    };
                }
                $callbacks[$key] = $callback;
                if (count($callbacks) >= 1024) {
                    unset($callbacks[key($callbacks)]);
                }
                $connection->send($callback($request));
            } else {
                $connection->send(new Response(404, [], '<h1>404 Not Found</h1>'));
            }
        } catch (Throwable $e) {
            $connection->send(new Response(500, [], (string)$e));
        } finally {
            Context::destroy();
        }
    }

    /**
     * @return Worker|null
     */
    public static function worker(): ?Worker
    {
        return static::$worker;
    }

    /**
     * @return Request
     */
    public static function request(): Request
    {
        return Context::get(Request::class);
    }

    /**
     * @param string $path
     * @param callable|Closure $callback
     */
    public function get(string $path, callable|Closure $callback): void
    {
        $this->addRoute('GET', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|Closure $callback
     */
    public function post(string $path, callable|Closure $callback): void
    {
        $this->addRoute('POST', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|Closure $callback
     */
    public function put(string $path, callable|Closure $callback): void
    {
        $this->addRoute('PUT', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|Closure $callback
     */
    public function patch(string $path, callable|Closure $callback): void
    {
        $this->addRoute('PATCH', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|Closure $callback
     */
    public function delete(string $path, callable|Closure $callback): void
    {
        $this->addRoute('DELETE', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|Closure $callback
     */
    public function head(string $path, callable|Closure $callback): void
    {
        $this->addRoute('HEAD', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|Closure $callback
     */
    public function options(string $path, callable|Closure $callback): void
    {
        $this->addRoute('OPTIONS', $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|Closure $callback
     */
    public function any(string $path, callable|Closure $callback): void
    {
        $this->addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], $path, $callback);
    }

    /**
     * @param string $path
     * @param callable|Closure $callback
     */
    public function group(string $path, callable|Closure $callback): void
    {
        $this->pathPrefix = $path;
        $callback($this);
        $this->pathPrefix = '';
    }

    /**
     * start
     */
    public function start(): void
    {
        Worker::runAll();
    }

    /**
     * @param string|array $method
     * @param string $path
     * @param callable|Closure $callback
     */
    public function addRoute(string|array $method, string $path, callable|Closure $callback): void
    {
        $methods = (array)$method;
        foreach ($methods as $method) {
            $this->routeInfo[$method][] = [$this->pathPrefix . $path, $callback];
        }
    }
}
