# Moshii-Shell

📌 Overview

MOSHII-SHELL is a stealthy, feature-rich PHP web shell designed for penetration testing, red teaming, and security research. It provides an interactive command-line interface (CLI) directly in the browser, with built-in evasion techniques, multiple execution methods, and post-exploitation modules.
- It's enhanced version of p0wny-shell ==> https://github.com/flozz/p0wny-shell

✔ Lightweight & Undetectable – Minimal footprint, encrypted communications, and anti-forensic features.
✔ Multi-Execution Methods – Supports exec, shell_exec, system, passthru, popen, and proc_open.
✔ File Management – Upload, download, edit, and search files with ease.
⚡ Features
🔹 Core Functionality

✅ Interactive Shell – Execute commands directly in the target environment.
✅ File Upload/Download – Transfer files to and from the compromised system.
✅ Directory Navigation – Full cd, ls, pwd support with path auto-completion.
✅ Command History – Track executed commands with ↑/↓ navigation.
🔹 Evasion & Anti-Forensics

🔍 System Recon – Enumerate OS, users, processes, and network info.
🔓 Privilege Escalation Checks – Identifies misconfigurations (SUID, writable cron jobs).
📁 Credential Harvesting – Extracts browser passwords, SSH keys, and database credentials.
🔄 Persistence Mechanisms – Cron jobs, backdoor shells, and service installation.
🔹 Network & Lateral Movement
🔄 SSH & SMB Spraying – Automated password attacks for lateral movement.
📡 Multi-C2 Fallback – Supports HTTP, DNS, and ICMP exfiltration.
🚀 Installation & Usage
----------------------------------------------------------------------------------------------
📥 Deployment

  1-  Upload moshii-shell.php to the target web server (via file upload, RCE, etc.).
  2-  Go to directory of your shell
  3-  voila.. you got your own terminal (SHELL) into your browser.
  ----------------------------------------------------------------------------------------------
  Tried the tool on HackTheBox machine called (Mist)
  link of video :
  https://youtu.be/zGecglNKpa0
