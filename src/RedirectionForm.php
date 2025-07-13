<?php
namespace Arnavision\PaymentGateway;
use JsonSerializable;

class RedirectionForm implements JsonSerializable,\Stringable
{
    /**
     * Redirection form view's path
     *
     * @var string
     */
    protected static $viewPath;

    /**
     * The callable function that renders the given view
     *
     * @var callable
     */
    protected static $viewRenderer;
    public function __construct(protected string $action, protected array $inputs = [], protected string $method = 'POST')
    {
    }
    /**
     * Retrieve associated method.
     */
    public function getMethod() : string
    {
        return $this->method;
    }

    /**
     * Retrieve associated inputs
     */
    public function getInputs() : array
    {
        return $this->inputs;
    }

    /**
     * Retrieve associated action
     */
    public function getAction() : string
    {
        return $this->action;
    }
    public function toString() : string
    {
        return $this->render();
    }
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Retrieve default view path.
     */
    public static function getDefaultViewPath() : string
    {
        return dirname(__DIR__).'/resources/views/redirect-form.php';
    }
    /**
     * Retrieve view path.
     */
    public static function getViewPath() : string
    {
        return static::$viewPath ?? static::getDefaultViewPath();
    }

    /**
     * Set view renderer
     */
    public static function setViewRenderer(callable $renderer): void
    {
        static::$viewRenderer = $renderer;
    }
    /**
     * Retrieve default view renderer.
     */
    protected function getDefaultViewRenderer() : callable
    {
        return function (string $view, string $action, array $inputs, string $method): string|false {
            ob_start();

            require($view);

            return ob_get_clean();
        };
    }
    /**
     * Render form.
     */
    public function render() : string
    {
        $data = [
            "view" => static::getViewPath(),
            "action" => $this->getAction(),
            "inputs" => $this->getInputs(),
            "method" => $this->getMethod(),
        ];

        $renderer = is_callable(static::$viewRenderer) ? static::$viewRenderer : $this->getDefaultViewRenderer();

        return call_user_func_array($renderer, $data);
    }
    public function jsonSerialize()
    {
        return [
            'action' => $this->getAction(),
            'inputs' => $this->getInputs(),
            'method' => $this->getMethod(),
        ];
    }
}
