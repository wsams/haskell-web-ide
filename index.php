<?php

$sourceName = "code";
$sourceFile = "{$sourceName}.hs";
// Default code for editor
$code = "import Data.List\n\ndo = print 42";
// Set the language for syntax highlighting in Ace
$language = "haskell";

if ($_GET['a'] === "load") {
    if (file_exists($sourceFile)) {
        $code = file_get_contents($sourceFile);
    } else {
        file_put_contents($sourceFile, $code);
    }
    exit(json_encode([ "status" => "ok", "code" => $code ]));
} else if ($_GET['a'] === "compile") {
    // start: take version backup
    if (!file_exists("versions")) {
        mkdir("versions");
    }
    $now = date("YmdHis");
    $prev = file_get_contents($sourceFile);
    if (trim($prev) !== trim($_POST['code'])) {
        copy($sourceFile, "versions/{$now}.{$sourceFile}");
    }
    // end: take version backup
    file_put_contents($sourceFile, $_POST['code']);

	$start = getTime();
    $compiler = `ghc {$sourceFile} 2> compiler.err`;
	$end = getTime();
	$compileTime = $end - $start;

    $compiler = nl2br($compiler . file_get_contents("compiler.err"));

	$start = getTime();
    $execution = `./{$sourceName}`;
	$end = getTime();
	$executionTime = $end - $start;

    exit(json_encode([
		"status" => "ok",
		"message" => "Compiled",
		"compiler" => $compiler,
		"execution" => $execution,
		"compileTime" => $compileTime,
		"executionTime" => $executionTime
	]));
} else if ($_GET['a'] === "versions") {
    $versions = glob("versions/*");
    $out = "";
    foreach ($versions as $version) {
        $out .= "<a target=\"_blank\" href=\"{$version}\">{$version}</a><br />";
    }
    exit($out);
} else if ($_GET['a'] === "version") {
    exit(json_encode([ "status" => "ok", "version" => `ghc --version` ]));
}

function getTime() {
    $t = explode(" ", microtime());
    return (float) $t[0] + (float) $t[1];
}

$html = <<<eof
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <title>Haskell Web IDE</title>
        <link rel="icon" type="image/ico" href="favicon.ico">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

        <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.min.css" crossorigin="anonymous">
        <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap-theme.min.css" crossorigin="anonymous">
        <style type="text/css">
            body {
                background-color:gray;
            }
            .swap {
                margin:0;
                padding:0.75em;
                width:100%;
                background-color:#404040;
                color:silver;
                cursor:pointer;
                text-align:center;
            }
            #editor-text {
                width:100%;
                outline:0;
                border-top:0;
                padding:4px;
                margin:0;
            }
            #compile {
                margin:4px;
            }
            #compiler, #execution {
                width:100%;
                margin:0;
                padding:4px;
                font-family:monospace;
            }
            .message {
                font-weight:bold;
                display:block;
            }
        </style>
    </head>
    <body>
        <div class="swap" title="Click anywhere to toggle the editor and output console">
            Swap View <span class="message"></span>
        </div>
        <div id="editor">
            <div id="editor-text"></div>
            <button class="btn btn-success" id="compile">Compile</button> <a class="btn btn-primary" target="_blank" href="index.php?a=versions">Versions</a>
        </div>
        <div id="output">
            <div id="compiler"></div>
			<div id="compileTime"></div>
            <div id="execution"></div>
			<div id="executionTime"></div>
        </div>
        <div class="version"></div>

        <script type="text/javascript" src="node_modules/jquery/dist/jquery.min.js" crossorigin="anonymous"></script>

        <script type="text/javascript" src="node_modules/fastclick/lib/fastclick.js" crossorigin="anonymous"></script>

        <script type="text/javascript" src="node_modules/bootstrap/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>

        <script src="node_modules/ace-builds/src-min-noconflict/ace.js" type="text/javascript"
            charset="utf-8" crossorigin="anonymous"></script>

        <script type="text/javascript">
            var hidden = "output";
            var compiling = false;

            // fastclick.js
            FastClick.attach(document.body);

            function swap() {
                if (hidden === "output") {
                    hidden = "editor";
                    $(".message").html("");
                    $("#output").show();
                    $("#editor").hide();
                } else {
                    hidden = "output";
                    $("#output").hide();
                    $("#editor").show();
                    if (!compiling) {
                        $(".message").html("");
                    }
                }
            }

            $(function() {
                $("#output").hide();

                var winY = parseInt($(window).height(), 10);
                var editorY = Math.round(winY * 0.75);
                $("#editor-text").css("height", editorY + "px");

                var editor = ace.edit("editor-text");
                editor.setTheme("ace/theme/solarized_dark");
                editor.getSession().setMode("ace/mode/{$language}");

                var mobile = window.innerWidth <= 800 && window.innerHeight <= 800;
                if (mobile) {
                    editor.renderer.setShowGutter(false);
                } else {
                    editor.renderer.setShowGutter(true);
                }

                $.getJSON("index.php?a=load", function(json) {
                    editor.setValue(json.code, -1);
                });

                $.getJSON("index.php?a=version", function(json) {
                    $(".version").html(json.version);
                });

                $(".swap").on("click", function() {
                    swap();
                });

                $("#compile").on("click", function() {
                    // TODO: given the position ({row: x, column: y})
                    //       save on each compile to file, then when
                    //       refreshing the page put your cursor back
                    //       to that position.
                    //var cursorPosition = editor.selection.getCursor();

                    compiling = true;
                    var content = editor.getValue();
                    // These two lines remove the previous output.
                    //$("#compiler").html("");
                    //$("#execution").html("");
                    swap();
                    $(".message").html("Compiling...Please Wait<br /><img src='assets/loader.gif' alt='loading' />");
                    $.ajax({
                        type: "POST",
                        url: "index.php?a=compile",
                        data: "code=" + encodeURIComponent(content),
                        dataType: "json",
                        success: function(json) {
                            compiling = false;
                            $(".message").html("Done");
                            $("#compiler").html(json.compiler);
                            $("#execution").html(json.execution);
							$("#compileTime").html("<p>Compiled code in " + json.compileTime + " seconds.");
							$("#executionTime").html("<p>Compiled code in " + json.executionTime + " seconds.");
                        }
                    });
                });
            });
        </script>
    </body>
</html>
eof;

print($html);
