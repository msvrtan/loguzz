<?php

namespace Loguzz\Formatter;

use GuzzleHttp\Cookie\CookieJarInterface;
use Psr\Http\Message\RequestInterface;

abstract class AbstractRequestFormatter
{
    protected $options = [];

    public function __construct()
    {
        $this->initializeOptions();
    }

    final protected function initializeOptions(array $options = [])
    {
        $this->options = empty($options) ? [] : $options;
    }

    protected function extractArguments(RequestInterface $request, array $options): void
    {
        $this->initializeOptions();
        $this->extractHttpMethodArgument($request);
        $this->extractBodyArgument($request);
        $this->extractCookiesArgument($request, $options);
        $this->extractHeadersArgument($request);
        $this->extractUrlArgument($request);
    }

    final protected function extractHttpMethodArgument(RequestInterface $request): void
    {
        $this->options['method'] = $request->getMethod();
    }

    final protected function extractBodyArgument(RequestInterface $request): void
    {
        $body = $request->getBody();

        if ($body->isSeekable()) {
            $previousPosition = $body->tell();
            $body->rewind();
        }

        $contents = $body->getContents();

        if ($body->isSeekable()) {
            $body->seek($previousPosition);
        }

        if ($contents) {
            // clean input of null bytes
            $contents = str_replace(chr(0), '', $contents);
            $this->options['data'] = $contents;
        }

        //if get request has data Add G otherwise curl will make a post request
        if (!empty($this->options['data']) && ('GET' === $request->getMethod())) {
            $this->options['method'] = 'GET';
        }
    }

    final protected function extractCookiesArgument(RequestInterface $request, array $options): void
    {
        if (!isset($options['cookies']) || !$options['cookies'] instanceof CookieJarInterface) {
            return;
        }

        if ($cookies = $options['cookies']->toArray()) {
            $this->options['cookies'] = array_map(function ($cookie) {
                return [
                    'name' => $cookie['Name'] ?? null,
                    'value' => $cookie['Value'] ?? null,
                    'domain' => $cookie['Domain'] ?? null,
                    'path' => $cookie['Path'] ?? '/',
                    'max-age' => $cookie['Max-age'] ?? null,
                    'expires' => $cookie['Expires'] ?? null,
                    'secure' => $cookie['Secure'] ?? false,
                    'discard' => $cookie['Discard'] ?? false,
                    'httponly' => $cookie['Httponly'] ?? false,
                ];
            }, $cookies);
        }
    }

    final protected function extractHeadersArgument(RequestInterface $request): void
    {
        foreach ($request->getHeaders() as $name => $header) {
            if ('host' === strtolower($name) && $header[0] === $request->getUri()->getHost()) {
                continue;
            }

            if ('cookie' === strtolower($name)) {
                continue;
            }

            if ('user-agent' === strtolower($name)) {
                $this->options['user-agent'] = $header[0];
                continue;
            }

            foreach ((array) $header as $headerValue) {
                $this->options['headers'][$name] = $headerValue;
            }
        }
    }

    final protected function extractUrlArgument(RequestInterface $request): void
    {
        $this->options['url'] = (string) $request->getUri()->withFragment('');
    }

    abstract public function format(RequestInterface $request, array $options = []);
}
