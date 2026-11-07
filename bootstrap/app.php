<?php

declare(strict_types=1);

namespace {
    $vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        spl_autoload_register(static function (string $class): void {
            $baseDir = dirname(__DIR__);
            $prefixes = [
                'App\\' => $baseDir . '/app/',
                'Database\\Seeders\\' => $baseDir . '/database/seeders/',
                'Database\\Factories\\' => $baseDir . '/database/factories/',
                'Tests\\' => $baseDir . '/tests/',
            ];

            foreach ($prefixes as $prefix => $dir) {
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    continue;
                }
                $relativeClass = substr($class, $len);
                $file = $dir . str_replace('\\', '/', $relativeClass) . '.php';
                if (is_file($file)) {
                    require_once $file;
                    return;
                }
            }
        });
    }

    if (!function_exists('base_path')) {
        function base_path(string $path = ''): string
        {
            $base = dirname(__DIR__);
            return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : $base;
        }
    }

    if (!function_exists('database_path')) {
        function database_path(string $path = ''): string
        {
            return base_path('database' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
        }
    }

    if (!function_exists('env')) {
        function env(string $key, mixed $default = null): mixed
        {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            if ($val === false || $val === null) {
                return $default;
            }
            if (is_string($val)) {
                $lower = strtolower($val);
                if ($lower === 'true' || $lower === '(true)') {
                    return true;
                }
                if ($lower === 'false' || $lower === '(false)') {
                    return false;
                }
                if ($lower === 'null' || $lower === '(null)') {
                    return null;
                }
            }
            return $val;
        }
    }

    if (!function_exists('config')) {
        function config(?string $key = null, mixed $default = null): mixed
        {
            static $loadedConfigs = [];
            if ($key === null) {
                return $loadedConfigs;
            }
            $parts = explode('.', $key);
            $file = array_shift($parts);
            if (!isset($loadedConfigs[$file])) {
                $configPath = base_path("config/{$file}.php");
                $loadedConfigs[$file] = is_file($configPath) ? require $configPath : [];
            }
            $value = $loadedConfigs[$file];
            foreach ($parts as $part) {
                if (!is_array($value) || !array_key_exists($part, $value)) {
                    return $default;
                }
                $value = $value[$part];
            }
            return $value;
        }
    }

    if (!function_exists('response')) {
        function response(mixed $content = '', int $status = 200, array $headers = []): object
        {
            return new class($content, $status, $headers) {
                public function __construct(
                    private mixed $content = '',
                    private int $status = 200,
                    private array $headers = []
                ) {
                }

                public function json(array $data, int $status = 200, array $headers = []): \Illuminate\Http\JsonResponse
                {
                    return new \Illuminate\Http\JsonResponse($data, $status, $headers);
                }

                public function getStatusCode(): int
                {
                    return $this->status;
                }

                public function getContent(): string
                {
                    return is_string($this->content) ? $this->content : (string) json_encode($this->content);
                }

                public function getHeaders(): array
                {
                    return $this->headers;
                }
            };
        }
    }
}

namespace Illuminate\Http {
    if (!class_exists(JsonResponse::class)) {
        class JsonResponse
        {
            public function __construct(
                private array $data = [],
                private int $status = 200,
                private array $headers = []
            ) {
            }

            public function getStatusCode(): int
            {
                return $this->status;
            }

            public function getData(bool $assoc = true): mixed
            {
                return $assoc ? $this->data : (object) $this->data;
            }

            public function getContent(): string
            {
                return (string) json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            public function getHeaders(): array
            {
                return $this->headers;
            }
        }
    }

    if (!class_exists(Request::class)) {
        class Request
        {
            private ?object $authenticatedUser = null;

            public function __construct(
                private array $payload = [],
                private array $headers = [],
                private array $routeParams = []
            ) {
            }

            public static function createFromPayload(array $payload = [], ?object $user = null, array $routeParams = []): self
            {
                $req = new self($payload, [], $routeParams);
                $req->setUserResolver(static fn () => $user);
                return $req;
            }

            public function all(): array
            {
                return $this->payload;
            }

            public function input(?string $key = null, mixed $default = null): mixed
            {
                if ($key === null) {
                    return $this->payload;
                }
                return $this->payload[$key] ?? $default;
            }

            public function boolean(string $key, bool $default = false): bool
            {
                if (!array_key_exists($key, $this->payload)) {
                    return $default;
                }
                return filter_var($this->payload[$key], FILTER_VALIDATE_BOOLEAN);
            }

            public function integer(string $key, int $default = 0): int
            {
                return isset($this->payload[$key]) ? (int) $this->payload[$key] : $default;
            }

            public function string(string $key, string $default = ''): string
            {
                return isset($this->payload[$key]) ? (string) $this->payload[$key] : $default;
            }

            public function setUserResolver(callable $resolver): self
            {
                $this->authenticatedUser = $resolver();
                return $this;
            }

            public function user(): ?object
            {
                return $this->authenticatedUser;
            }

            public function route(?string $param = null, mixed $default = null): mixed
            {
                if ($param === null) {
                    return $this->routeParams;
                }
                return $this->routeParams[$param] ?? $default;
            }

            public function header(string $key, mixed $default = null): mixed
            {
                return $this->headers[$key] ?? $default;
            }
        }
    }
}

namespace Illuminate\Foundation\Http {
    if (!class_exists(FormRequest::class)) {
        class FormRequest extends \Illuminate\Http\Request
        {
            public function authorize(): bool
            {
                return true;
            }

            public function rules(): array
            {
                return [];
            }

            public function validated(): array
            {
                return $this->all();
            }
        }
    }
}

namespace Illuminate\Routing {
    if (!class_exists(Controller::class)) {
        abstract class Controller
        {
        }
    }
}

namespace Illuminate\Database\Eloquent {
    if (!class_exists(Model::class)) {
        abstract class Model implements \ArrayAccess, \JsonSerializable
        {
            protected string $table = '';
            protected string $primaryKey = 'id';
            public bool $incrementing = true;
            protected string $keyType = 'int';
            public bool $timestamps = true;
            protected array $fillable = [];
            protected array $hidden = [];
            protected array $casts = [];
            protected array $attributes = [];

            public function __construct(array $attributes = [])
            {
                $this->fill($attributes);
            }

            public function fill(array $attributes): static
            {
                foreach ($attributes as $key => $value) {
                    $this->attributes[$key] = $value;
                }
                return $this;
            }

            public function getTable(): string
            {
                return $this->table;
            }

            public function getFillable(): array
            {
                return $this->fillable;
            }

            public function getHidden(): array
            {
                return $this->hidden;
            }

            public function getKeyName(): string
            {
                return $this->primaryKey;
            }

            public function getIncrementing(): bool
            {
                return $this->incrementing;
            }

            public function getKeyType(): string
            {
                return $this->keyType;
            }

            public function __get(string $key): mixed
            {
                return $this->attributes[$key] ?? null;
            }

            public function __set(string $key, mixed $value): void
            {
                $this->attributes[$key] = $value;
            }

            public function __isset(string $key): bool
            {
                return isset($this->attributes[$key]);
            }

            public function offsetExists(mixed $offset): bool
            {
                return isset($this->attributes[$offset]);
            }

            public function offsetGet(mixed $offset): mixed
            {
                return $this->attributes[$offset] ?? null;
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
                if ($offset !== null) {
                    $this->attributes[$offset] = $value;
                }
            }

            public function offsetUnset(mixed $offset): void
            {
                unset($this->attributes[$offset]);
            }

            public function toArray(): array
            {
                $data = $this->attributes;
                foreach ($this->hidden as $hiddenKey) {
                    unset($data[$hiddenKey]);
                }
                return $data;
            }

            public function jsonSerialize(): mixed
            {
                return $this->toArray();
            }
        }
    }
}

namespace Illuminate\Foundation\Auth {
    if (!class_exists(User::class)) {
        class User extends \Illuminate\Database\Eloquent\Model
        {
        }
    }
}

namespace Illuminate\Database\Migrations {
    if (!class_exists(Migration::class)) {
        abstract class Migration
        {
            abstract public function up(): void;
            abstract public function down(): void;
        }
    }
}

namespace Illuminate\Database\Schema {
    if (!class_exists(Blueprint::class)) {
        class Blueprint
        {
            public array $columns = [];
            public array $indexes = [];

            public function __construct(public string $table)
            {
            }

            public function __call(string $name, array $arguments): self
            {
                $this->columns[] = ['method' => $name, 'args' => $arguments];
                return $this;
            }
        }
    }
}

namespace Illuminate\Support\Facades {
    if (!class_exists(Schema::class)) {
        class Schema
        {
            public static array $tables = [];

            public static function create(string $table, callable $callback): void
            {
                $blueprint = new \Illuminate\Database\Schema\Blueprint($table);
                $callback($blueprint);
                self::$tables[$table] = $blueprint;
            }

            public static function dropIfExists(string $table): void
            {
                unset(self::$tables[$table]);
            }
        }
    }

    if (!class_exists(Route::class)) {
        class Route
        {
            public static array $routes = [];

            public static function __callStatic(string $method, array $args): object
            {
                self::$routes[] = ['method' => $method, 'args' => $args];
                return new class {
                    public function __call(string $name, array $arguments): self
                    {
                        return $this;
                    }
                    public function group(callable $callback): self
                    {
                        $callback();
                        return $this;
                    }
                };
            }
        }
    }
}

namespace Illuminate\Database {
    if (!class_exists(Seeder::class)) {
        abstract class Seeder
        {
            abstract public function run(): void;
        }
    }
}

namespace Illuminate\Contracts\Queue {
    if (!interface_exists(ShouldQueue::class)) {
        interface ShouldQueue
        {
        }
    }
}

namespace Illuminate\Foundation\Queue {
    if (!trait_exists(Queueable::class)) {
        trait Queueable
        {
            public ?string $queue = null;
        }
    }
}

namespace {
    if (class_exists(\Illuminate\Foundation\Application::class)) {
        return \Illuminate\Foundation\Application::configure(basePath: dirname(__DIR__))
            ->withRouting(
                web: __DIR__ . '/../routes/web.php',
                api: __DIR__ . '/../routes/api.php',
                health: '/up',
            )
            ->withMiddleware(function ($middleware): void {
                $middleware->alias([
                    'role' => \App\Http\Middleware\EnsureRole::class,
                    'double_blind' => \App\Http\Middleware\EnsureDoubleBlindUnlocked::class,
                ]);
            })
            ->withExceptions(function ($exceptions): void {
                //
            })->create();
    }

    return new class {
        public function basePath(string $path = ''): string
        {
            return base_path($path);
        }
    };
}
