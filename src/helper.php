<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

//------------------------
// ThinkPHP 助手函数
//-------------------------

use think\App;
use think\Container;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Cookie;
use think\facade\Env;
use think\facade\Event;
use think\facade\Lang;
use think\facade\Log;
use think\facade\Request;
use think\facade\Route;
use think\facade\Session;
use think\Response;
use think\route\Url as UrlBuild;
use think\Validate;

if (!function_exists('abort')) {
    /**
     * 抛出HTTP异常
     * @param integer|Response $code    状态码 或者 Response对象实例
     * @param string           $message 错误信息
     * @param array            $header  参数
     */
    function abort($code, string $message = null, array $header = [])
    {
        if ($code instanceof Response) {
            throw new HttpResponseException($code);
        } else {
            throw new HttpException($code, $message, null, $header);
        }
    }
}

if (!function_exists('app')) {
    /**
     * 快速获取容器中的实例 支持依赖注入
     * @param string $name        类名或标识 默认获取当前应用实例
     * @param array  $args        参数
     * @param bool   $newInstance 是否每次创建新的实例
     * @return object|App
     */
    function app(string $name = '', array $args = [], bool $newInstance = false)
    {
        return Container::getInstance()->make($name ?: App::class, $args, $newInstance);
    }
}

if (!function_exists('bind')) {
    /**
     * 绑定一个类到容器
     * @param  string|array $abstract 类标识、接口（支持批量绑定）
     * @param  mixed        $concrete 要绑定的类、闭包或者实例
     * @return Container
     */
    function bind($abstract, $concrete = null)
    {
        return Container::getInstance()->bind($abstract, $concrete);
    }
}

if (!function_exists('cache')) {
    /**
     * 缓存管理
     * @param  mixed  $name    缓存名称，如果为数组表示进行缓存设置
     * @param  mixed  $value   缓存值
     * @param  mixed  $options 缓存参数
     * @param  string $tag     缓存标签
     * @return mixed
     */
    function cache($name, $value = '', $options = null, $tag = null)
    {
        if (is_array($name)) {
            // 缓存初始化
            return Cache::connect($name);
        }

        if ('' === $value) {
            // 获取缓存
            return 0 === strpos($name, '?') ? Cache::has(substr($name, 1)) : Cache::get($name);
        } elseif (is_null($value)) {
            // 删除缓存
            return Cache::delete($name);
        }

        // 缓存数据
        if (is_array($options)) {
            $expire = $options['expire'] ?? null; //修复查询缓存无法设置过期时间
        } else {
            $expire = $options;
        }

        if (is_null($tag)) {
            return Cache::set($name, $value, $expire);
        } else {
            return Cache::tag($tag)->set($name, $value, $expire);
        }
    }
}

if (!function_exists('class_basename')) {
    /**
     * 获取类名(不包含命名空间)
     *
     * @param  mixed $class 类名
     * @return string
     */
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('class_uses_recursive')) {
    /**
     *获取一个类里所有用到的trait，包括父类的
     *
     * @param  mixed $class 类名
     * @return array
     */
    function class_uses_recursive($class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];
        $classes = array_merge([$class => $class], class_parents($class));
        foreach ($classes as $class) {
            $results += trait_uses_recursive($class);
        }

        return array_unique($results);
    }
}

if (!function_exists('config')) {
    /**
     * 获取和设置配置参数
     * @param  string|array $name  参数名
     * @param  mixed        $value 参数值
     * @return mixed
     */
    function config($name = '', $value = null)
    {
        if (is_array($name)) {
            return Config::set($name, $value);
        }

        return 0 === strpos($name, '?') ? Config::has(substr($name, 1)) : Config::get($name, $value);
    }
}

if (!function_exists('cookie')) {
    /**
     * Cookie管理
     * @param  string $name   cookie名称
     * @param  mixed  $value  cookie值
     * @param  mixed  $option 参数
     * @return mixed
     */
    function cookie(string $name, $value = '', $option = null)
    {
        if (is_null($value)) {
            // 删除
            Cookie::delete($name);
        } elseif ('' === $value) {
            // 获取
            return 0 === strpos($name, '?') ? Cookie::has(substr($name, 1)) : Cookie::get($name);
        } else {
            // 设置
            return Cookie::set($name, $value, $option);
        }
    }
}

if (!function_exists('download')) {
    /**
     * 获取\think\response\Download对象实例
     * @param  string $filename 要下载的文件
     * @param  string $name     显示文件名
     * @param  bool   $content  是否为内容
     * @param  int    $expire   有效期（秒）
     * @return \think\response\File
     */
    function download(string $filename, string $name = '', bool $content = false, int $expire = 180)
    {
        return Response::create($filename, 'file')->name($name)->isContent($content)->expire($expire);
    }
}

if (!function_exists('dump')) {
    /**
     * 浏览器友好的变量输出
     * @param  mixed  $vars 要输出的变量
     * @return void
     */
    function dump(...$vars)
    {
        ob_start();
        var_dump(...$vars);

        $output = ob_get_clean();
        $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);

        if (PHP_SAPI == 'cli') {
            $output = PHP_EOL . $output . PHP_EOL;
        } else {
            if (!extension_loaded('xdebug')) {
                $output = htmlspecialchars($output, ENT_SUBSTITUTE);
            }
            $output = '<pre>' . $output . '</pre>';
        }

        echo $output;
    }
}

if (!function_exists('env')) {
    /**
     * 获取环境变量值
     * @access public
     * @param  string $name    环境变量名（支持二级 .号分割）
     * @param  string $default 默认值
     * @return mixed
     */
    function env(string $name = null, $default = null)
    {
        return Env::get($name, $default);
    }
}

if (!function_exists('event')) {
    /**
     * 触发事件
     * @param  mixed $event 事件名（或者类名）
     * @param  mixed $args  参数
     * @return mixed
     */
    function event($event, $args = null)
    {
        return Event::trigger($event, $args);
    }
}

if (!function_exists('halt')) {
    /**
     * 调试变量并且中断输出
     * @param mixed $vars 调试变量或者信息
     */
    function halt(...$vars)
    {
        dump(...$vars);

        throw new HttpResponseException(new Response);
    }
}

if (!function_exists('input')) {
    /**
     * 获取输入数据 支持默认值和过滤
     * @param  string $key     获取的变量名
     * @param  mixed  $default 默认值
     * @param  string $filter  过滤方法
     * @return mixed
     */
    function input(string $key = '', $default = null, $filter = '')
    {
        if (0 === strpos($key, '?')) {
            $key = substr($key, 1);
            $has = true;
        }

        if ($pos = strpos($key, '.')) {
            // 指定参数来源
            $method = substr($key, 0, $pos);
            if (in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'route', 'param', 'request', 'session', 'cookie', 'server', 'env', 'path', 'file'])) {
                $key = substr($key, $pos + 1);
            } else {
                $method = 'param';
            }
        } else {
            // 默认为自动判断
            $method = 'param';
        }

        return isset($has) ?
        request()->has($key, $method) :
        request()->$method($key, $default, $filter);
    }
}

if (!function_exists('invoke')) {
    /**
     * 调用反射实例化对象或者执行方法 支持依赖注入
     * @param  mixed $call 类名或者callable
     * @param  array $args 参数
     * @return mixed
     */
    function invoke($call, array $args = [])
    {
        if (is_callable($call)) {
            return Container::getInstance()->invoke($call, $args);
        }

        return Container::getInstance()->invokeClass($call, $args);
    }
}

if (!function_exists('json')) {
    /**
     * 获取\think\response\Json对象实例
     * @param  mixed $data    返回的数据
     * @param  int   $code    状态码
     * @param  array $header  头部
     * @param  array $options 参数
     * @return \think\response\Json
     */
    function json($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'json', $code)->header($header)->options($options);
    }
}

if (!function_exists('jsonp')) {
    /**
     * 获取\think\response\Jsonp对象实例
     * @param  mixed $data    返回的数据
     * @param  int   $code    状态码
     * @param  array $header  头部
     * @param  array $options 参数
     * @return \think\response\Jsonp
     */
    function jsonp($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'jsonp', $code)->header($header)->options($options);
    }
}

if (!function_exists('lang')) {
    /**
     * 获取语言变量值
     * @param  string $name 语言变量名
     * @param  array  $vars 动态变量值
     * @param  string $lang 语言
     * @return mixed
     */
    function lang(string $name, array $vars = [], string $lang = '')
    {
        return Lang::get($name, $vars, $lang);
    }
}

if (!function_exists('parse_name')) {
    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @param  string $name    字符串
     * @param  int    $type    转换类型
     * @param  bool   $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    function parse_name(string $name, int $type = 0, bool $ucfirst = true): string
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);

            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }

        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}

if (!function_exists('redirect')) {
    /**
     * 获取\think\response\Redirect对象实例
     * @param  mixed         $url    重定向地址 支持Url::build方法的地址
     * @param  array|integer $params 额外参数
     * @param  int           $code   状态码
     * @return \think\response\Redirect
     */
    function redirect($url = [], $params = [], $code = 302)
    {
        if (is_integer($params)) {
            $code   = $params;
            $params = [];
        }

        return Response::create($url, 'redirect', $code)->params($params);
    }
}

if (!function_exists('request')) {
    /**
     * 获取当前Request对象实例
     * @return Request
     */
    function request()
    {
        return app('request');
    }
}

if (!function_exists('response')) {
    /**
     * 创建普通 Response 对象实例
     * @param  mixed      $data   输出数据
     * @param  int|string $code   状态码
     * @param  array      $header 头信息
     * @param  string     $type
     * @return Response
     */
    function response($data = '', $code = 200, $header = [], $type = 'html')
    {
        return Response::create($data, $type, $code)->header($header);
    }
}

if (!function_exists('session')) {
    /**
     * Session管理
     * @param  string $name  session名称
     * @param  mixed  $value session值
     * @return mixed
     */
    function session(string $name = null, $value = '')
    {
        if (is_null($name)) {
            // 清除
            Session::clear();
        } elseif (is_null($value)) {
            // 删除
            Session::delete($name);
        } elseif ('' === $value) {
            // 判断或获取
            return 0 === strpos($name, '?') ? Session::has(substr($name, 1)) : Session::get($name);
        } else {
            // 设置
            Session::set($name, $value);
        }
    }
}

if (!function_exists('token')) {
    /**
     * 获取Token令牌
     * @param  string $name 令牌名称
     * @param  mixed  $type 令牌生成方法
     * @return string
     */
    function token(string $name = '__token__', string $type = 'md5'): string
    {
        return Request::buildToken($name, $type);
    }
}

if (!function_exists('token_field')) {
    /**
     * 生成令牌隐藏表单
     * @param  string $name 令牌名称
     * @param  mixed  $type 令牌生成方法
     * @return string
     */
    function token_field(string $name = '__token__', string $type = 'md5'): string
    {
        $token = Request::buildToken($name, $type);

        return '<input type="hidden" name="' . $name . '" value="' . $token . '" />';
    }
}

if (!function_exists('token_meta')) {
    /**
     * 生成令牌meta
     * @param  string $name 令牌名称
     * @param  mixed  $type 令牌生成方法
     * @return string
     */
    function token_meta(string $name = '__token__', string $type = 'md5'): string
    {
        $token = Request::buildToken($name, $type);

        return '<meta name="csrf-token" content="' . $token . '">';
    }
}

if (!function_exists('trace')) {
    /**
     * 记录日志信息
     * @param  mixed  $log   log信息 支持字符串和数组
     * @param  string $level 日志级别
     * @return array|void
     */
    function trace($log = '[think]', string $level = 'log')
    {
        if ('[think]' === $log) {
            return Log::getLog();
        }

        Log::record($log, $level);
    }
}

if (!function_exists('trait_uses_recursive')) {
    /**
     * 获取一个trait里所有引用到的trait
     *
     * @param  string $trait Trait
     * @return array
     */
    function trait_uses_recursive(string $trait): array
    {
        $traits = class_uses($trait);
        foreach ($traits as $trait) {
            $traits += trait_uses_recursive($trait);
        }

        return $traits;
    }
}

if (!function_exists('url')) {
    /**
     * Url生成
     * @param string      $url    路由地址
     * @param array       $vars   变量
     * @param bool|string $suffix 生成的URL后缀
     * @param bool|string $domain 域名
     * @return UrlBuild
     */
    function url(string $url = '', array $vars = [], $suffix = true, $domain = false): UrlBuild
    {
        return Route::buildUrl($url, $vars)->suffix($suffix)->domain($domain);
    }
}

if (!function_exists('validate')) {
    /**
     * 生成验证对象
     * @param  string|array $validate 验证器类名或者验证规则数组
     * @param  array        $message  错误提示信息
     * @param  bool         $batch    是否批量验证
     * @return Validate
     */
    function validate($validate = '', array $message = [], bool $batch = false): Validate
    {
        if (is_array($validate) || '' === $validate) {
            $v = new Validate();
            if (is_array($validate)) {
                $v->rule($validate);
            }
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                list($validate, $scene) = explode('.', $validate);
            }

            $class = false !== strpos($validate, '\\') ? $validate : app()->parseClass('validate', $validate);

            $v = new $class();

            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        return $v->message($message)->batch($batch)->failException(true);
    }
}

if (!function_exists('view')) {
    /**
     * 渲染模板输出
     * @param string    $template 模板文件
     * @param array     $vars 模板变量
     * @param int       $code 状态码
     * @param callable  $filter 内容过滤
     * @return \think\response\View
     */
    function view(string $template = '', $vars = [], $code = 200, $filter = null)
    {
        return Response::create($template, 'view', $code)->assign($vars)->filter($filter);
    }
}

if (!function_exists('display')) {
    /**
     * 渲染模板输出
     * @param string    $content 渲染内容
     * @param array     $vars 模板变量
     * @param int       $code 状态码
     * @param callable  $filter 内容过滤
     * @return \think\response\View
     */
    function display(string $content, $vars = [], $code = 200, $filter = null)
    {
        return Response::create($template, 'view', $code)->isContent(true)->assign($vars)->filter($filter);
    }
}

if (!function_exists('xml')) {
    /**
     * 获取\think\response\Xml对象实例
     * @param  mixed $data    返回的数据
     * @param  int   $code    状态码
     * @param  array $header  头部
     * @param  array $options 参数
     * @return \think\response\Xml
     */
    function xml($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'xml', $code)->header($header)->options($options);
    }
}
