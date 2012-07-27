<?php

class Twig_Environment_WP extends Twig_Environment
{
    protected function writeCacheFile($file, $content)
    {
        parent::writeCacheFile($file, $content);

        // Compile cached file into bytecode cache if apc is enabled 
        if (function_exists("apc_compile_file")) {
          apc_compile_file($file);
        }
    }
}

class RenderHelper {
  function render_error($title, $description) {
    ob_end_clean();
    require "templates/error_template.php";
    die();
  }

  function render_template($name, $locals = array()) {
    $valid_filenames = array("$name.haml.twig", "$name.haml", "$name.html.php", "$name.php");
    foreach ($valid_filenames as $filename) {
      $path = Wordless::join_paths(Wordless::theme_views_path(), $filename);
      if (is_file($path)) {
        $template_path = $path;
        $template_name = $filename;
        $format = pathinfo($path, PATHINFO_EXTENSION);
        break;
      }
    }

    if (!isset($template_path)) {
      render_error("Template missing", "<strong>Ouch!!</strong> It seems that <code>$name.haml.twig</code> or <code>$name.html.php</code> doesn't exist!");
    }

    switch ($format) {
      case 'haml':
        $cache = Wordless::preference("template.cache_enabled",false);

        if (is_string($cache) && !is_writable($cache)) {
          render_error("Template cache dir not writable", "<strong>Ouch!!</strong> It seems that the <code>$cache</code> directory is not writable by the server! Go fix it!");
        }

        $haml = new MtHaml\Environment('twig', array('enable_escaper' => false));
        $twig_loader = new Twig_Loader_Filesystem(Wordless::theme_views_path());
        $twig_loader = new MtHaml\Support\Twig\Loader($haml,$twig_loader);
        $twig = new Twig_Environment_WP($twig_loader, array(
            'autoescape' => false,
            'cache' => $cache,
            'debug' => true,

        ));
        $twig->addFilter('lorem', new Twig_Filter_Function('placeholder_text'));
        $twig->registerUndefinedFunctionCallback(function ($name) {
            if (function_exists($name)) {
                return new Twig_Function_Function($name);
            }

            return false;
        });

        try {
          echo $twig->render($template_name, $locals);
        } catch (MtHaml\Exception\SyntaxErrorException $e) {
          render_error("SyntaxErrorException", $e->getMessage());
        }catch(Twig_Error_Syntax $e){
          render_error("SyntaxErrorException", $e->getMessage());
        }

        break;
      case 'php':
        extract($locals);
        include $template_path;
        break;
    }

  }

  function yield() {
    global $current_view;
    render_template($current_view);
  }

  function render_view($name, $layout = 'default') {
    ob_start();
    global $current_view;
    $current_view = $name;
    render_template("layouts/$layout");
    ob_flush();
  }
}

Wordless::register_helper("RenderHelper");
