<?php
/**
 * Command library - PHP port of src/commands.js
 * Builds the same curated + templated list of {cmd, desc, cat} entries.
 */
final class Commands
{
    public const CATEGORIES = [
        'useful'     => 'Useful',
        'linux'      => 'Linux',
        'vps'        => 'VPS',
        'docker'     => 'Docker',
        'git'        => 'Git',
        'networking' => 'Networking',
        'system'     => 'System',
        'auto'       => 'Auto',
        'review'     => 'Review',
        'suggestion' => 'Suggestion',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;

        $list = [];
        $seen = [];
        $add = function (string $cmd, string $desc, string $cat) use (&$list, &$seen): void {
            $cmd = trim((string) preg_replace('/\s+/', ' ', $cmd));
            if ($cmd === '' || in_array($cmd, $seen, true)) return;
            $seen[] = $cmd;
            $list[] = ['cmd' => $cmd, 'desc' => $desc !== '' ? $desc : '', 'cat' => $cat];
        };
        $addCurated = function (string $cmd, string $desc, string $cat) use ($add): void {
            $add($cmd, $desc, $cat);
            $add('sudo ' . $cmd, 'Run "' . $cmd . '" with root privileges', $cat);
            $add($cmd . ' --help', 'Show usage and options for "' . $cmd . '"', $cat);
        };

        $curated = [
            // ---------------- Useful ----------------
            [ 'c' => 'ls', 'd' => 'List files in the current directory', 't' => 'useful' ],
            [ 'c' => 'ls -la', 'd' => 'List all files with details (hidden included)', 't' => 'useful' ],
            [ 'c' => 'ls -lh', 'd' => 'List files with human-readable sizes', 't' => 'useful' ],
            [ 'c' => 'ls -lrt', 'd' => 'List files sorted by modification time (newest last)', 't' => 'useful' ],
            [ 'c' => 'cd ..', 'd' => 'Go up one directory level', 't' => 'useful' ],
            [ 'c' => 'cd ~', 'd' => 'Go to your home directory', 't' => 'useful' ],
            [ 'c' => 'cd -', 'd' => 'Go to the previous directory', 't' => 'useful' ],
            [ 'c' => 'pwd', 'd' => 'Print the current working directory', 't' => 'useful' ],
            [ 'c' => 'cat file', 'd' => 'Print the contents of a file', 't' => 'useful' ],
            [ 'c' => 'less file', 'd' => 'View a file page by page', 't' => 'useful' ],
            [ 'c' => 'more file', 'd' => 'View a file page by page (simple)', 't' => 'useful' ],
            [ 'c' => 'head file', 'd' => 'Show the first 10 lines of a file', 't' => 'useful' ],
            [ 'c' => 'tail file', 'd' => 'Show the last 10 lines of a file', 't' => 'useful' ],
            [ 'c' => 'tail -f file', 'd' => 'Follow a file as it grows (live log)', 't' => 'useful' ],
            [ 'c' => 'grep pattern file', 'd' => 'Search for a pattern in a file', 't' => 'useful' ],
            [ 'c' => 'grep -rn pattern dir', 'd' => 'Recursively search a directory', 't' => 'useful' ],
            [ 'c' => 'find . -name "*.log"', 'd' => 'Find files by name', 't' => 'useful' ],
            [ 'c' => 'which cmd', 'd' => 'Show the path of a command', 't' => 'useful' ],
            [ 'c' => 'whoami', 'd' => 'Show the current username', 't' => 'useful' ],
            [ 'c' => 'id', 'd' => 'Show user and group IDs', 't' => 'useful' ],
            [ 'c' => 'date', 'd' => 'Show the current date and time', 't' => 'useful' ],
            [ 'c' => 'cal', 'd' => 'Show a calendar', 't' => 'useful' ],
            [ 'c' => 'uptime', 'd' => 'Show how long the system has been running', 't' => 'useful' ],
            [ 'c' => 'uname -a', 'd' => 'Show kernel and system information', 't' => 'useful' ],
            [ 'c' => 'df -h', 'd' => 'Show disk usage in human-readable form', 't' => 'useful' ],
            [ 'c' => 'du -sh *', 'd' => 'Show size of each item in the directory', 't' => 'useful' ],
            [ 'c' => 'free -h', 'd' => 'Show memory usage', 't' => 'useful' ],
            [ 'c' => 'top', 'd' => 'Show live system processes', 't' => 'useful' ],
            [ 'c' => 'htop', 'd' => 'Interactive process viewer', 't' => 'useful' ],
            [ 'c' => 'ps aux', 'd' => 'Show all running processes', 't' => 'useful' ],
            [ 'c' => 'kill -9 pid', 'd' => 'Force-kill a process', 't' => 'useful' ],
            [ 'c' => 'history', 'd' => 'Show command history', 't' => 'useful' ],
            [ 'c' => 'clear', 'd' => 'Clear the terminal screen', 't' => 'useful' ],
            [ 'c' => 'echo "Hello"', 'd' => 'Print text to the terminal', 't' => 'useful' ],
            [ 'c' => 'man command', 'd' => 'Show the manual page for a command', 't' => 'useful' ],
            [ 'c' => 'whatis command', 'd' => 'Show a one-line description of a command', 't' => 'useful' ],
            [ 'c' => 'alias ll="ls -la"', 'd' => 'Create a command alias', 't' => 'useful' ],
            [ 'c' => 'export VAR=value', 'd' => 'Set an environment variable', 't' => 'useful' ],
            [ 'c' => 'env', 'd' => 'List all environment variables', 't' => 'useful' ],
            [ 'c' => 'nano file', 'd' => 'Edit a file with the nano editor', 't' => 'useful' ],
            [ 'c' => 'vim file', 'd' => 'Edit a file with the vim editor', 't' => 'useful' ],
            [ 'c' => 'cp source dest', 'd' => 'Copy a file', 't' => 'useful' ],
            [ 'c' => 'mv source dest', 'd' => 'Move or rename a file', 't' => 'useful' ],
            [ 'c' => 'rm file', 'd' => 'Delete a file', 't' => 'useful' ],
            [ 'c' => 'mkdir -p a/b/c', 'd' => 'Create nested directories', 't' => 'useful' ],
            [ 'c' => 'touch file', 'd' => 'Create an empty file', 't' => 'useful' ],
            [ 'c' => 'ln -s target link', 'd' => 'Create a symbolic link', 't' => 'useful' ],
            [ 'c' => 'file file', 'd' => 'Show the file type', 't' => 'useful' ],
            [ 'c' => 'stat file', 'd' => 'Show detailed file metadata', 't' => 'useful' ],
            [ 'c' => 'tar -czvf a.tar.gz dir', 'd' => 'Create a compressed archive', 't' => 'useful' ],
            [ 'c' => 'tar -xzvf a.tar.gz', 'd' => 'Extract a compressed archive', 't' => 'useful' ],
            [ 'c' => 'zip -r a.zip dir', 'd' => 'Create a zip archive', 't' => 'useful' ],
            [ 'c' => 'unzip a.zip', 'd' => 'Extract a zip archive', 't' => 'useful' ],
            [ 'c' => 'gzip file', 'd' => 'Compress a file with gzip', 't' => 'useful' ],
            [ 'c' => 'gunzip file.gz', 'd' => 'Decompress a gzip file', 't' => 'useful' ],
            [ 'c' => 'curl URL', 'd' => 'Fetch a URL', 't' => 'useful' ],
            [ 'c' => 'wget URL', 'd' => 'Download a file from a URL', 't' => 'useful' ],
            [ 'c' => 'ssh user@host', 'd' => 'Connect to a remote server via SSH', 't' => 'useful' ],
            [ 'c' => 'scp file user@host:/dest', 'd' => 'Copy a file to a remote server', 't' => 'useful' ],
            [ 'c' => 'rsync -avz src/ dest/', 'd' => 'Sync files and directories', 't' => 'useful' ],
            [ 'c' => 'wc -l file', 'd' => 'Count lines in a file', 't' => 'useful' ],
            [ 'c' => 'sort file', 'd' => 'Sort lines of a file', 't' => 'useful' ],
            [ 'c' => 'uniq file', 'd' => 'Remove duplicate lines', 't' => 'useful' ],
            [ 'c' => 'cut -d: -f1 file', 'd' => 'Cut fields out of each line', 't' => 'useful' ],
            [ 'c' => 'tee file', 'd' => 'Write output to a file and stdout', 't' => 'useful' ],

            // ---------------- Linux ----------------
            [ 'c' => 'useradd -m user', 'd' => 'Create a new user with a home directory', 't' => 'linux' ],
            [ 'c' => 'usermod -aG sudo user', 'd' => 'Add a user to the sudo group', 't' => 'linux' ],
            [ 'c' => 'userdel -r user', 'd' => 'Delete a user and their home directory', 't' => 'linux' ],
            [ 'c' => 'passwd user', 'd' => 'Change a user password', 't' => 'linux' ],
            [ 'c' => 'chpasswd', 'd' => 'Update passwords in batch mode', 't' => 'linux' ],
            [ 'c' => 'groupadd group', 'd' => 'Create a new group', 't' => 'linux' ],
            [ 'c' => 'groupdel group', 'd' => 'Delete a group', 't' => 'linux' ],
            [ 'c' => 'chmod 755 file', 'd' => 'Change file permissions', 't' => 'linux' ],
            [ 'c' => 'chmod +x script.sh', 'd' => 'Make a script executable', 't' => 'linux' ],
            [ 'c' => 'chown user:group file', 'd' => 'Change file ownership', 't' => 'linux' ],
            [ 'c' => 'chgrp group file', 'd' => 'Change file group ownership', 't' => 'linux' ],
            [ 'c' => 'umask 022', 'd' => 'Set default file permission mask', 't' => 'linux' ],
            [ 'c' => 'su - user', 'd' => 'Switch to another user', 't' => 'linux' ],
            [ 'c' => 'sudo -i', 'd' => 'Open a root shell', 't' => 'linux' ],
            [ 'c' => 'visudo', 'd' => 'Edit the sudoers file safely', 't' => 'linux' ],
            [ 'c' => 'mount /dev/sda1 /mnt', 'd' => 'Mount a filesystem', 't' => 'linux' ],
            [ 'c' => 'umount /mnt', 'd' => 'Unmount a filesystem', 't' => 'linux' ],
            [ 'c' => 'lsblk -f', 'd' => 'List block devices with filesystems', 't' => 'linux' ],
            [ 'c' => 'blkid', 'd' => 'Show block device UUIDs', 't' => 'linux' ],
            [ 'c' => 'fdisk -l', 'd' => 'List disk partitions', 't' => 'linux' ],
            [ 'c' => 'parted -l', 'd' => 'List partitions with parted', 't' => 'linux' ],
            [ 'c' => 'mkfs.ext4 /dev/sdb1', 'd' => 'Create an ext4 filesystem', 't' => 'linux' ],
            [ 'c' => 'fsck /dev/sda1', 'd' => 'Check and repair a filesystem', 't' => 'linux' ],
            [ 'c' => 'resize2fs /dev/sda1', 'd' => 'Resize an ext filesystem', 't' => 'linux' ],
            [ 'c' => 'dd if=/dev/sda of=/tmp/img bs=4M', 'd' => 'Copy data block by block', 't' => 'linux' ],
            [ 'c' => 'apt update', 'd' => 'Update package lists (Debian/Ubuntu)', 't' => 'linux' ],
            [ 'c' => 'apt upgrade -y', 'd' => 'Upgrade all packages', 't' => 'linux' ],
            [ 'c' => 'apt install package', 'd' => 'Install a package', 't' => 'linux' ],
            [ 'c' => 'apt remove package', 'd' => 'Remove a package', 't' => 'linux' ],
            [ 'c' => 'apt autoremove', 'd' => 'Remove unneeded packages', 't' => 'linux' ],
            [ 'c' => 'apt purge package', 'd' => 'Remove a package and its config', 't' => 'linux' ],
            [ 'c' => 'dpkg -i package.deb', 'd' => 'Install a .deb package', 't' => 'linux' ],
            [ 'c' => 'dpkg -l', 'd' => 'List installed packages', 't' => 'linux' ],
            [ 'c' => 'dpkg -L package', 'd' => 'List files installed by a package', 't' => 'linux' ],
            [ 'c' => 'dnf install package', 'd' => 'Install a package (Fedora/RHEL)', 't' => 'linux' ],
            [ 'c' => 'yum update -y', 'd' => 'Update packages (CentOS 7)', 't' => 'linux' ],
            [ 'c' => 'pacman -Syu', 'd' => 'Full system upgrade (Arch)', 't' => 'linux' ],
            [ 'c' => 'snap install package', 'd' => 'Install a snap package', 't' => 'linux' ],
            [ 'c' => 'flatpak install app', 'd' => 'Install a flatpak application', 't' => 'linux' ],
            [ 'c' => 'systemctl status service', 'd' => 'Show the status of a service', 't' => 'linux' ],
            [ 'c' => 'journalctl -xe', 'd' => 'Show recent system logs with explanations', 't' => 'linux' ],
            [ 'c' => 'sysctl -a', 'd' => 'Show all kernel parameters', 't' => 'linux' ],
            [ 'c' => 'update-alternatives --config editor', 'd' => 'Change the default editor', 't' => 'linux' ],
            [ 'c' => 'timedatectl', 'd' => 'Show system time and timezone', 't' => 'linux' ],
            [ 'c' => 'hostnamectl', 'd' => 'Show or set the hostname', 't' => 'linux' ],
            [ 'c' => 'localectl', 'd' => 'Show the system locale', 't' => 'linux' ],
            [ 'c' => 'locale-gen', 'd' => 'Generate locale data', 't' => 'linux' ],
            [ 'c' => 'nproc', 'd' => 'Show the number of CPU cores', 't' => 'linux' ],
            [ 'c' => 'lscpu', 'd' => 'Show CPU architecture details', 't' => 'linux' ],
            [ 'c' => 'lsusb', 'd' => 'List USB devices', 't' => 'linux' ],
            [ 'c' => 'lspci', 'd' => 'List PCI devices', 't' => 'linux' ],
            [ 'c' => 'dmesg', 'd' => 'Show kernel ring buffer messages', 't' => 'linux' ],
            [ 'c' => 'ldconfig -p', 'd' => 'Show the shared library cache', 't' => 'linux' ],
            [ 'c' => 'ldd /bin/ls', 'd' => 'Show library dependencies of a binary', 't' => 'linux' ],
            [ 'c' => 'strace -p pid', 'd' => 'Trace system calls of a process', 't' => 'linux' ],
            [ 'c' => 'ltrace cmd', 'd' => 'Trace library calls', 't' => 'linux' ],

            // ---------------- VPS ----------------
            [ 'c' => 'curl ifconfig.me', 'd' => 'Show your public IP address', 't' => 'vps' ],
            [ 'c' => 'curl -s icanhazip.com', 'd' => 'Show your public IP address', 't' => 'vps' ],
            [ 'c' => 'cat /etc/os-release', 'd' => 'Show the operating system details', 't' => 'vps' ],
            [ 'c' => 'lsb_release -a', 'd' => 'Show the Linux distribution version', 't' => 'vps' ],
            [ 'c' => 'nohup cmd &', 'd' => 'Run a command in the background', 't' => 'vps' ],
            [ 'c' => 'screen -S name', 'd' => 'Start a persistent screen session', 't' => 'vps' ],
            [ 'c' => 'screen -r name', 'd' => 'Reattach to a screen session', 't' => 'vps' ],
            [ 'c' => 'tmux new -s name', 'd' => 'Start a persistent tmux session', 't' => 'vps' ],
            [ 'c' => 'tmux attach -t name', 'd' => 'Attach to a tmux session', 't' => 'vps' ],
            [ 'c' => 'pm2 list', 'd' => 'List PM2 managed processes', 't' => 'vps' ],
            [ 'c' => 'pm2 start app.js', 'd' => 'Start a Node app with PM2', 't' => 'vps' ],
            [ 'c' => 'pm2 monit', 'd' => 'Monitor PM2 processes', 't' => 'vps' ],
            [ 'c' => 'pm2 logs', 'd' => 'Stream PM2 process logs', 't' => 'vps' ],
            [ 'c' => 'pm2 startup', 'd' => 'Set up PM2 to start on boot', 't' => 'vps' ],
            [ 'c' => 'pm2 save', 'd' => 'Save the current PM2 process list', 't' => 'vps' ],
            [ 'c' => 'forever start app.js', 'd' => 'Run a Node app with forever', 't' => 'vps' ],
            [ 'c' => 'ss -tulpn', 'd' => 'Show listening TCP/UDP ports', 't' => 'vps' ],
            [ 'c' => 'netstat -tulpn', 'd' => 'Show network ports and programs', 't' => 'vps' ],
            [ 'c' => 'iptables -L -n -v', 'd' => 'Show iptables rules', 't' => 'vps' ],
            [ 'c' => 'ufw status verbose', 'd' => 'Show the firewall status', 't' => 'vps' ],
            [ 'c' => 'ufw allow 22/tcp', 'd' => 'Allow SSH through the firewall', 't' => 'vps' ],
            [ 'c' => 'certbot --nginx -d example.com', 'd' => 'Get an SSL certificate via certbot', 't' => 'vps' ],
            [ 'c' => 'certbot renew --dry-run', 'd' => 'Test automatic certificate renewal', 't' => 'vps' ],
            [ 'c' => 'nginx -t', 'd' => 'Test the nginx configuration', 't' => 'vps' ],
            [ 'c' => 'nginx -s reload', 'd' => 'Reload the nginx configuration', 't' => 'vps' ],
            [ 'c' => 'apachectl configtest', 'd' => 'Test the Apache configuration', 't' => 'vps' ],
            [ 'c' => 'logrotate -f /etc/logrotate.conf', 'd' => 'Force run log rotation', 't' => 'vps' ],
            [ 'c' => 'crontab -e', 'd' => 'Edit the crontab', 't' => 'vps' ],
            [ 'c' => 'crontab -l', 'd' => 'List cron jobs', 't' => 'vps' ],
            [ 'c' => 'swapoff -a && swapon -a', 'd' => 'Reload swap space', 't' => 'vps' ],
            [ 'c' => 'fallocate -l 2G /swapfile', 'd' => 'Create a 2GB swap file', 't' => 'vps' ],
            [ 'c' => 'mkswap /swapfile', 'd' => 'Format a swap file', 't' => 'vps' ],
            [ 'c' => 'iotop', 'd' => 'Show I/O usage per process', 't' => 'vps' ],
            [ 'c' => 'iostat', 'd' => 'Show CPU and disk I/O statistics', 't' => 'vps' ],
            [ 'c' => 'vmstat 1', 'd' => 'Show system performance stats every second', 't' => 'vps' ],
            [ 'c' => 'sar -u 1 5', 'd' => 'Report CPU usage over time', 't' => 'vps' ],
            [ 'c' => 'fail2ban-client status', 'd' => 'Show fail2ban jail status', 't' => 'vps' ],
            [ 'c' => 'rkhunter --check', 'd' => 'Check for rootkits', 't' => 'vps' ],
            [ 'c' => 'lynis audit system', 'd' => 'Run a full system security audit', 't' => 'vps' ],
            [ 'c' => 'who', 'd' => 'Show who is logged in', 't' => 'vps' ],
            [ 'c' => 'w', 'd' => 'Show who is logged in and what they are doing', 't' => 'vps' ],
            [ 'c' => 'last', 'd' => 'Show last logins', 't' => 'vps' ],
            [ 'c' => 'lastb', 'd' => 'Show failed login attempts', 't' => 'vps' ],
            [ 'c' => 'ssh-keygen -t ed25519', 'd' => 'Generate an SSH key pair', 't' => 'vps' ],
            [ 'c' => 'ssh-copy-id user@host', 'd' => 'Install your SSH key on a server', 't' => 'vps' ],
            [ 'c' => 'tune2fs -l /dev/sda1', 'd' => 'Show ext filesystem details', 't' => 'vps' ],
            [ 'c' => 'systemctl enable nginx', 'd' => 'Enable a service on boot', 't' => 'vps' ],
            [ 'c' => 'systemctl disable nginx', 'd' => 'Disable a service from boot', 't' => 'vps' ],
            [ 'c' => 'glances', 'd' => 'All-in-one system monitor', 't' => 'vps' ],

            // ---------------- Docker ----------------
            [ 'c' => 'docker version', 'd' => 'Show Docker version info', 't' => 'docker' ],
            [ 'c' => 'docker info', 'd' => 'Show Docker system-wide info', 't' => 'docker' ],
            [ 'c' => 'docker run -d nginx', 'd' => 'Run a container in the background', 't' => 'docker' ],
            [ 'c' => 'docker run -it ubuntu bash', 'd' => 'Run an interactive container', 't' => 'docker' ],
            [ 'c' => 'docker run --rm -p 8080:80 nginx', 'd' => 'Run a container with port mapping', 't' => 'docker' ],
            [ 'c' => 'docker ps', 'd' => 'List running containers', 't' => 'docker' ],
            [ 'c' => 'docker ps -a', 'd' => 'List all containers', 't' => 'docker' ],
            [ 'c' => 'docker images', 'd' => 'List local images', 't' => 'docker' ],
            [ 'c' => 'docker pull nginx', 'd' => 'Pull an image from a registry', 't' => 'docker' ],
            [ 'c' => 'docker push image', 'd' => 'Push an image to a registry', 't' => 'docker' ],
            [ 'c' => 'docker build -t myapp .', 'd' => 'Build an image from a Dockerfile', 't' => 'docker' ],
            [ 'c' => 'docker exec -it container bash', 'd' => 'Open a shell inside a container', 't' => 'docker' ],
            [ 'c' => 'docker logs -f container', 'd' => 'Stream container logs', 't' => 'docker' ],
            [ 'c' => 'docker inspect container', 'd' => 'Show detailed container info', 't' => 'docker' ],
            [ 'c' => 'docker top container', 'd' => 'Show processes in a container', 't' => 'docker' ],
            [ 'c' => 'docker stats', 'd' => 'Show live container resource usage', 't' => 'docker' ],
            [ 'c' => 'docker port container', 'd' => 'Show container port mappings', 't' => 'docker' ],
            [ 'c' => 'docker stop container', 'd' => 'Stop a container', 't' => 'docker' ],
            [ 'c' => 'docker start container', 'd' => 'Start a container', 't' => 'docker' ],
            [ 'c' => 'docker restart container', 'd' => 'Restart a container', 't' => 'docker' ],
            [ 'c' => 'docker rm container', 'd' => 'Remove a container', 't' => 'docker' ],
            [ 'c' => 'docker rm -f container', 'd' => 'Force-remove a running container', 't' => 'docker' ],
            [ 'c' => 'docker rmi image', 'd' => 'Remove an image', 't' => 'docker' ],
            [ 'c' => 'docker commit container image', 'd' => 'Create an image from a container', 't' => 'docker' ],
            [ 'c' => 'docker tag image repo:tag', 'd' => 'Tag an image', 't' => 'docker' ],
            [ 'c' => 'docker cp container:/path ./dest', 'd' => 'Copy files from a container', 't' => 'docker' ],
            [ 'c' => 'docker network ls', 'd' => 'List Docker networks', 't' => 'docker' ],
            [ 'c' => 'docker network create net', 'd' => 'Create a Docker network', 't' => 'docker' ],
            [ 'c' => 'docker volume ls', 'd' => 'List Docker volumes', 't' => 'docker' ],
            [ 'c' => 'docker volume create vol', 'd' => 'Create a Docker volume', 't' => 'docker' ],
            [ 'c' => 'docker system df', 'd' => 'Show Docker disk usage', 't' => 'docker' ],
            [ 'c' => 'docker system prune -a', 'd' => 'Remove unused containers, images and networks', 't' => 'docker' ],
            [ 'c' => 'docker save -o img.tar image', 'd' => 'Save an image to a tar file', 't' => 'docker' ],
            [ 'c' => 'docker load -i img.tar', 'd' => 'Load an image from a tar file', 't' => 'docker' ],
            [ 'c' => 'docker export -o c.tar container', 'd' => 'Export a container filesystem', 't' => 'docker' ],
            [ 'c' => 'docker import c.tar image', 'd' => 'Import a container filesystem as an image', 't' => 'docker' ],
            [ 'c' => 'docker diff container', 'd' => 'Show container filesystem changes', 't' => 'docker' ],
            [ 'c' => 'docker history image', 'd' => 'Show image layer history', 't' => 'docker' ],
            [ 'c' => 'docker events', 'd' => 'Stream Docker events', 't' => 'docker' ],
            [ 'c' => 'docker rename old new', 'd' => 'Rename a container', 't' => 'docker' ],
            [ 'c' => 'docker attach container', 'd' => 'Attach to a running container', 't' => 'docker' ],
            [ 'c' => 'docker pause container', 'd' => 'Pause a container', 't' => 'docker' ],
            [ 'c' => 'docker unpause container', 'd' => 'Unpause a container', 't' => 'docker' ],
            [ 'c' => 'docker compose up -d', 'd' => 'Start services defined in compose file', 't' => 'docker' ],
            [ 'c' => 'docker compose down', 'd' => 'Stop and remove compose services', 't' => 'docker' ],
            [ 'c' => 'docker compose logs -f', 'd' => 'Stream compose service logs', 't' => 'docker' ],
            [ 'c' => 'docker compose ps', 'd' => 'List compose services', 't' => 'docker' ],
            [ 'c' => 'docker compose exec app bash', 'd' => 'Run a command in a compose service', 't' => 'docker' ],

            // ---------------- Git ----------------
            [ 'c' => 'git init', 'd' => 'Initialize a repository', 't' => 'git' ],
            [ 'c' => 'git clone URL', 'd' => 'Clone a repository', 't' => 'git' ],
            [ 'c' => 'git status', 'd' => 'Show the working tree status', 't' => 'git' ],
            [ 'c' => 'git add .', 'd' => 'Stage all changes', 't' => 'git' ],
            [ 'c' => 'git commit -m "message"', 'd' => 'Commit staged changes', 't' => 'git' ],
            [ 'c' => 'git log', 'd' => 'Show commit history', 't' => 'git' ],
            [ 'c' => 'git log --oneline', 'd' => 'Show compact commit history', 't' => 'git' ],
            [ 'c' => 'git diff', 'd' => 'Show unstaged changes', 't' => 'git' ],
            [ 'c' => 'git diff --staged', 'd' => 'Show staged changes', 't' => 'git' ],
            [ 'c' => 'git branch', 'd' => 'List branches', 't' => 'git' ],
            [ 'c' => 'git branch -a', 'd' => 'List all branches (including remote)', 't' => 'git' ],
            [ 'c' => 'git checkout -b feature', 'd' => 'Create and switch to a branch', 't' => 'git' ],
            [ 'c' => 'git checkout main', 'd' => 'Switch to a branch', 't' => 'git' ],
            [ 'c' => 'git merge branch', 'd' => 'Merge a branch into the current branch', 't' => 'git' ],
            [ 'c' => 'git rebase main', 'd' => 'Reapply commits on top of main', 't' => 'git' ],
            [ 'c' => 'git stash', 'd' => 'Stash uncommitted changes', 't' => 'git' ],
            [ 'c' => 'git stash pop', 'd' => 'Restore stashed changes', 't' => 'git' ],
            [ 'c' => 'git tag v1.0.0', 'd' => 'Create a lightweight tag', 't' => 'git' ],
            [ 'c' => 'git tag -a v1.0.0 -m "release"', 'd' => 'Create an annotated tag', 't' => 'git' ],
            [ 'c' => 'git push origin main', 'd' => 'Push commits to the remote', 't' => 'git' ],
            [ 'c' => 'git pull origin main', 'd' => 'Pull changes from the remote', 't' => 'git' ],
            [ 'c' => 'git fetch --all', 'd' => 'Fetch all remotes', 't' => 'git' ],
            [ 'c' => 'git remote -v', 'd' => 'Show configured remotes', 't' => 'git' ],
            [ 'c' => 'git remote add origin URL', 'd' => 'Add a remote', 't' => 'git' ],
            [ 'c' => 'git config --global user.name "name"', 'd' => 'Set the global git username', 't' => 'git' ],
            [ 'c' => 'git config --global user.email "email"', 'd' => 'Set the global git email', 't' => 'git' ],
            [ 'c' => 'git reset --hard HEAD', 'd' => 'Discard all uncommitted changes', 't' => 'git' ],
            [ 'c' => 'git reset --soft HEAD~1', 'd' => 'Undo the last commit keeping changes', 't' => 'git' ],
            [ 'c' => 'git revert HEAD', 'd' => 'Revert the last commit', 't' => 'git' ],
            [ 'c' => 'git cherry-pick hash', 'd' => 'Apply a commit from another branch', 't' => 'git' ],
            [ 'c' => 'git show hash', 'd' => 'Show details of a commit', 't' => 'git' ],
            [ 'c' => 'git blame file', 'd' => 'Show who changed each line', 't' => 'git' ],
            [ 'c' => 'git clean -fd', 'd' => 'Remove untracked files and directories', 't' => 'git' ],
            [ 'c' => 'git gc', 'd' => 'Run garbage collection', 't' => 'git' ],
            [ 'c' => 'git fsck', 'd' => 'Verify repository integrity', 't' => 'git' ],
            [ 'c' => 'git reflog', 'd' => 'Show the reference log', 't' => 'git' ],
            [ 'c' => 'git submodule update --init --recursive', 'd' => 'Initialize submodules', 't' => 'git' ],
            [ 'c' => 'git archive -o out.zip HEAD', 'd' => 'Create an archive of the tree', 't' => 'git' ],
            [ 'c' => 'git shortlog -sn', 'd' => 'Show commit counts per author', 't' => 'git' ],
            [ 'c' => 'git describe --tags', 'd' => 'Show the closest tag to HEAD', 't' => 'git' ],

            // ---------------- Networking ----------------
            [ 'c' => 'ping -c 4 host', 'd' => 'Ping a host 4 times', 't' => 'networking' ],
            [ 'c' => 'traceroute host', 'd' => 'Trace the route to a host', 't' => 'networking' ],
            [ 'c' => 'tracepath host', 'd' => 'Trace path to a host (no root)', 't' => 'networking' ],
            [ 'c' => 'mtr host', 'd' => 'Combined ping and traceroute', 't' => 'networking' ],
            [ 'c' => 'dig example.com', 'd' => 'DNS lookup', 't' => 'networking' ],
            [ 'c' => 'dig example.com +short', 'd' => 'Short DNS lookup', 't' => 'networking' ],
            [ 'c' => 'nslookup example.com', 'd' => 'Query DNS servers', 't' => 'networking' ],
            [ 'c' => 'host example.com', 'd' => 'Simple DNS lookup', 't' => 'networking' ],
            [ 'c' => 'whois example.com', 'd' => 'Show domain registration info', 't' => 'networking' ],
            [ 'c' => 'curl -v URL', 'd' => 'Fetch a URL with verbose output', 't' => 'networking' ],
            [ 'c' => 'curl -I URL', 'd' => 'Fetch only the HTTP headers', 't' => 'networking' ],
            [ 'c' => 'curl -O URL', 'd' => 'Download and save with the original name', 't' => 'networking' ],
            [ 'c' => 'wget -q URL', 'd' => 'Download quietly', 't' => 'networking' ],
            [ 'c' => 'nc -zv host port', 'd' => 'Test a TCP port', 't' => 'networking' ],
            [ 'c' => 'nmap -sV host', 'd' => 'Scan services and versions', 't' => 'networking' ],
            [ 'c' => 'nmap -p 1-1000 host', 'd' => 'Scan a port range', 't' => 'networking' ],
            [ 'c' => 'arp -a', 'd' => 'Show the ARP cache', 't' => 'networking' ],
            [ 'c' => 'ip addr show', 'd' => 'Show all network addresses', 't' => 'networking' ],
            [ 'c' => 'ip link show', 'd' => 'Show network interfaces', 't' => 'networking' ],
            [ 'c' => 'ip route show', 'd' => 'Show the routing table', 't' => 'networking' ],
            [ 'c' => 'ip neigh show', 'd' => 'Show the neighbor (ARP) table', 't' => 'networking' ],
            [ 'c' => 'ip tunnel show', 'd' => 'Show tunnel interfaces', 't' => 'networking' ],
            [ 'c' => 'ifconfig', 'd' => 'Show network interface config', 't' => 'networking' ],
            [ 'c' => 'route -n', 'd' => 'Show the kernel routing table', 't' => 'networking' ],
            [ 'c' => 'ss -s', 'd' => 'Show socket statistics', 't' => 'networking' ],
            [ 'c' => 'ss -t state established', 'd' => 'Show established TCP connections', 't' => 'networking' ],
            [ 'c' => 'ethtool eth0', 'd' => 'Show NIC settings', 't' => 'networking' ],
            [ 'c' => 'tcpdump -i any port 80', 'd' => 'Capture HTTP traffic', 't' => 'networking' ],
            [ 'c' => 'tcpdump -n -i eth0', 'd' => 'Capture traffic on an interface', 't' => 'networking' ],
            [ 'c' => 'lsof -i', 'd' => 'List open network files', 't' => 'networking' ],
            [ 'c' => 'ab -n 100 -c 10 URL', 'd' => 'Benchmark a web server', 't' => 'networking' ],
            [ 'c' => 'iperf -s', 'd' => 'Run an iperf server for bandwidth tests', 't' => 'networking' ],
            [ 'c' => 'iperf -c host', 'd' => 'Run an iperf client', 't' => 'networking' ],
            [ 'c' => 'resolvectl status', 'd' => 'Show DNS resolver status', 't' => 'networking' ],
            [ 'c' => 'dhclient -r && dhclient', 'd' => 'Renew the DHCP lease', 't' => 'networking' ],
            [ 'c' => 'ss -tln', 'd' => 'Show listening TCP ports', 't' => 'networking' ],
            [ 'c' => 'ss -uln', 'd' => 'Show listening UDP ports', 't' => 'networking' ],
            [ 'c' => 'ufw enable', 'd' => 'Enable the firewall', 't' => 'networking' ],
            [ 'c' => 'ufw deny 23/tcp', 'd' => 'Deny telnet through the firewall', 't' => 'networking' ],
            [ 'c' => 'iptables -S', 'd' => 'List all iptables rules', 't' => 'networking' ],
            [ 'c' => 'iptables -F', 'd' => 'Flush all iptables rules', 't' => 'networking' ],
            [ 'c' => 'nft list ruleset', 'd' => 'List nftables rules', 't' => 'networking' ],
            [ 'c' => 'openssl s_client -connect host:443', 'd' => 'Inspect an SSL/TLS certificate', 't' => 'networking' ],
            [ 'c' => 'hostname -I', 'd' => 'Show the server IP address', 't' => 'networking' ],
            [ 'c' => 'curl -s http://169.254.169.254/latest/meta-data/', 'd' => 'Query cloud metadata (AWS)', 't' => 'networking' ],

            // ---------------- System ----------------
            [ 'c' => 'systemctl list-units', 'd' => 'List loaded systemd units', 't' => 'system' ],
            [ 'c' => 'systemctl list-units --failed', 'd' => 'List failed units', 't' => 'system' ],
            [ 'c' => 'systemctl daemon-reload', 'd' => 'Reload systemd manager configuration', 't' => 'system' ],
            [ 'c' => 'systemd-analyze blame', 'd' => 'Show boot time per unit', 't' => 'system' ],
            [ 'c' => 'systemd-analyze critical-chain', 'd' => 'Show the boot critical chain', 't' => 'system' ],
            [ 'c' => 'systemd-analyze plot > boot.svg', 'd' => 'Export a boot analysis plot', 't' => 'system' ],
            [ 'c' => 'systemd-analyze security', 'd' => 'Show the security exposure of units', 't' => 'system' ],
            [ 'c' => 'systemd-cgtop', 'd' => 'Show control group resource usage', 't' => 'system' ],
            [ 'c' => 'systemd-cgls', 'd' => 'Show control group hierarchy', 't' => 'system' ],
            [ 'c' => 'systemd-run --user cmd', 'd' => 'Run a command as a transient unit', 't' => 'system' ],
            [ 'c' => 'loginctl list-sessions', 'd' => 'List login sessions', 't' => 'system' ],
            [ 'c' => 'loginctl terminate-user user', 'd' => 'Terminate all sessions of a user', 't' => 'system' ],
            [ 'c' => 'bootctl status', 'd' => 'Show the bootloader status', 't' => 'system' ],
            [ 'c' => 'lsmod', 'd' => 'List loaded kernel modules', 't' => 'system' ],
            [ 'c' => 'modprobe module', 'd' => 'Load a kernel module', 't' => 'system' ],
            [ 'c' => 'modinfo module', 'd' => 'Show info about a kernel module', 't' => 'system' ],
            [ 'c' => 'insmod module.ko', 'd' => 'Insert a kernel module', 't' => 'system' ],
            [ 'c' => 'rmmod module', 'd' => 'Remove a kernel module', 't' => 'system' ],
            [ 'c' => 'uptime -s', 'd' => 'Show when the system was started', 't' => 'system' ],
            [ 'c' => 'uptime -p', 'd' => 'Show uptime in a pretty format', 't' => 'system' ],
            [ 'c' => 'ps -ef', 'd' => 'Show all processes in full format', 't' => 'system' ],
            [ 'c' => 'ps aux --sort=-%mem', 'd' => 'Sort processes by memory usage', 't' => 'system' ],
            [ 'c' => 'ps aux --sort=-%cpu', 'd' => 'Sort processes by CPU usage', 't' => 'system' ],
            [ 'c' => 'pstree -p', 'd' => 'Show the process tree with PIDs', 't' => 'system' ],
            [ 'c' => 'pgrep -u user', 'd' => 'Find processes of a user', 't' => 'system' ],
            [ 'c' => 'pkill -f pattern', 'd' => 'Kill processes matching a pattern', 't' => 'system' ],
            [ 'c' => 'killall name', 'd' => 'Kill all processes with a name', 't' => 'system' ],
            [ 'c' => 'kill -SIGHUP pid', 'd' => 'Send the HUP signal to a process', 't' => 'system' ],
            [ 'c' => 'kill -SIGTERM pid', 'd' => 'Ask a process to terminate', 't' => 'system' ],
            [ 'c' => 'nice -n -5 cmd', 'd' => 'Run a command with higher priority', 't' => 'system' ],
            [ 'c' => 'renice -n -10 -p pid', 'd' => 'Change a process priority', 't' => 'system' ],
            [ 'c' => 'ionice -c 3 -p pid', 'd' => 'Set idle I/O priority for a process', 't' => 'system' ],
            [ 'c' => 'taskset -c 0-1 cmd', 'd' => 'Pin a command to specific CPUs', 't' => 'system' ],
            [ 'c' => 'ulimit -n', 'd' => 'Show the file descriptor limit', 't' => 'system' ],
            [ 'c' => 'ulimit -n 65535', 'd' => 'Raise the file descriptor limit', 't' => 'system' ],
            [ 'c' => 'free -m', 'd' => 'Show memory in megabytes', 't' => 'system' ],
            [ 'c' => 'vmstat -s', 'd' => 'Show memory statistics', 't' => 'system' ],
            [ 'c' => 'mpstat -P ALL 1', 'd' => 'Show per-CPU usage', 't' => 'system' ],
            [ 'c' => 'pidstat 1', 'd' => 'Report per-process stats', 't' => 'system' ],
            [ 'c' => 'sensors', 'd' => 'Show temperature sensors', 't' => 'system' ],
            [ 'c' => 'dmidecode -t memory', 'd' => 'Show memory hardware info', 't' => 'system' ],
            [ 'c' => 'lshw -short', 'd' => 'Show a hardware summary', 't' => 'system' ],
            [ 'c' => 'hdparm -t /dev/sda', 'd' => 'Benchmark disk read speed', 't' => 'system' ],
            [ 'c' => 'smartctl -a /dev/sda', 'd' => 'Show disk SMART data', 't' => 'system' ],
            [ 'c' => 'cat /proc/cpuinfo', 'd' => 'Show CPU information', 't' => 'system' ],
            [ 'c' => 'cat /proc/meminfo', 'd' => 'Show memory information', 't' => 'system' ],
            [ 'c' => 'cat /proc/loadavg', 'd' => 'Show the system load average', 't' => 'system' ],
            [ 'c' => 'cat /proc/uptime', 'd' => 'Show system uptime in seconds', 't' => 'system' ],
            [ 'c' => 'cat /proc/net/dev', 'd' => 'Show network device counters', 't' => 'system' ],
            [ 'c' => 'cat /proc/partitions', 'd' => 'Show partition information', 't' => 'system' ],
            [ 'c' => 'watch -n 2 free -h', 'd' => 'Watch memory usage every 2 seconds', 't' => 'system' ],
            [ 'c' => 'watch -n 2 uptime', 'd' => 'Watch the load average', 't' => 'system' ],

            // ---------------- Auto ----------------
            [ 'c' => 'crontab -e', 'd' => 'Edit cron jobs', 't' => 'auto' ],
            [ 'c' => 'crontab -l', 'd' => 'List cron jobs', 't' => 'auto' ],
            [ 'c' => 'crontab -r', 'd' => 'Remove all cron jobs', 't' => 'auto' ],
            [ 'c' => 'echo "0 2 * * * /backup.sh" | crontab -', 'd' => 'Install a cron job from stdin', 't' => 'auto' ],
            [ 'c' => 'at now + 1 hour', 'd' => 'Schedule a one-time job', 't' => 'auto' ],
            [ 'c' => 'atq', 'd' => 'List pending at jobs', 't' => 'auto' ],
            [ 'c' => 'atrm jobid', 'd' => 'Remove a pending at job', 't' => 'auto' ],
            [ 'c' => 'watch -n 60 cmd', 'd' => 'Run a command every 60 seconds', 't' => 'auto' ],
            [ 'c' => 'watch -d cmd', 'd' => 'Highlight differences between runs', 't' => 'auto' ],
            [ 'c' => 'watch -n 5 "ps aux | grep app"', 'd' => 'Watch a filtered process list', 't' => 'auto' ],
            [ 'c' => 'xargs -I {} cmd {} < list.txt', 'd' => 'Run a command for each line of input', 't' => 'auto' ],
            [ 'c' => 'find . -name "*.log" -exec rm {} \\;', 'd' => 'Find and delete log files', 't' => 'auto' ],
            [ 'c' => 'find . -name "*.bak" -exec mv {} /backup/ \\;', 'd' => 'Move files matching a pattern', 't' => 'auto' ],
            [ 'c' => 'seq 1 10 | xargs -n1 cmd', 'd' => 'Run a command 10 times', 't' => 'auto' ],
            [ 'c' => 'for i in {1..10}; do cmd; done', 'd' => 'Loop a command 10 times', 't' => 'auto' ],
            [ 'c' => 'while true; do cmd; sleep 60; done', 'd' => 'Run a command in an infinite loop', 't' => 'auto' ],
            [ 'c' => 'sleep 30 && cmd', 'd' => 'Run a command after 30 seconds', 't' => 'auto' ],
            [ 'c' => 'timeout 60 cmd', 'd' => 'Run a command with a time limit', 't' => 'auto' ],
            [ 'c' => 'nohup cmd > out.log 2>&1 &', 'd' => 'Run in the background with a log', 't' => 'auto' ],
            [ 'c' => 'setsid cmd', 'd' => 'Run a command in a new session', 't' => 'auto' ],
            [ 'c' => 'flock /tmp/lock cmd', 'd' => 'Run a command with a lock file', 't' => 'auto' ],
            [ 'c' => 'screen -d -m cmd', 'd' => 'Start a command in a detached screen', 't' => 'auto' ],
            [ 'c' => 'tmux new -d -s sess cmd', 'd' => 'Start a command in a detached tmux', 't' => 'auto' ],
            [ 'c' => 'tmux kill-server', 'd' => 'Kill all tmux sessions', 't' => 'auto' ],
            [ 'c' => 'rsync -avz --delete src/ dst/', 'd' => 'Mirror a directory with rsync', 't' => 'auto' ],
            [ 'c' => 'rsync -avz --exclude "*.tmp" src/ dst/', 'd' => 'Sync excluding temp files', 't' => 'auto' ],
            [ 'c' => 'logrotate -f /etc/logrotate.conf', 'd' => 'Rotate logs immediately', 't' => 'auto' ],
            [ 'c' => 'sed -i "s/old/new/g" file', 'd' => 'Replace text in place', 't' => 'auto' ],
            [ 'c' => 'awk \'{print $1}\' file', 'd' => 'Extract the first field of each line', 't' => 'auto' ],
            [ 'c' => 'cut -d: -f1-3 /etc/passwd', 'd' => 'Extract fields from a delimited file', 't' => 'auto' ],
            [ 'c' => 'sort -u file', 'd' => 'Sort and remove duplicates', 't' => 'auto' ],
            [ 'c' => 'uniq -c file', 'd' => 'Count unique lines', 't' => 'auto' ],
            [ 'c' => 'tr a-z A-Z < file', 'd' => 'Translate lowercase to uppercase', 't' => 'auto' ],
            [ 'c' => 'paste file1 file2', 'd' => 'Merge files line by line', 't' => 'auto' ],
            [ 'c' => 'split -l 100 file part_', 'd' => 'Split a file into chunks', 't' => 'auto' ],
            [ 'c' => 'shuf file', 'd' => 'Shuffle lines of a file', 't' => 'auto' ],
            [ 'c' => 'yes | cmd', 'd' => 'Automatically answer yes', 't' => 'auto' ],
            [ 'c' => 'python3 -m http.server 8080', 'd' => 'Serve the current directory over HTTP', 't' => 'auto' ],
            [ 'c' => 'curl -s -X POST URL -d "data"', 'd' => 'Send an automated POST request', 't' => 'auto' ],
            [ 'c' => 'mail -s "Alert" user@example.com < report.txt', 'd' => 'Email a report automatically', 't' => 'auto' ],

            // ---------------- Review ----------------
            [ 'c' => 'journalctl -p err -b', 'd' => 'Show errors since boot', 't' => 'review' ],
            [ 'c' => 'journalctl -f', 'd' => 'Follow all system logs', 't' => 'review' ],
            [ 'c' => 'journalctl --since today', 'd' => 'Show logs since today', 't' => 'review' ],
            [ 'c' => 'journalctl --disk-usage', 'd' => 'Show journal disk usage', 't' => 'review' ],
            [ 'c' => 'dmesg -T', 'd' => 'Show kernel messages with timestamps', 't' => 'review' ],
            [ 'c' => 'dmesg | grep -i error', 'd' => 'Show kernel errors', 't' => 'review' ],
            [ 'c' => 'tail -n 100 /var/log/syslog', 'd' => 'Review the last 100 syslog lines', 't' => 'review' ],
            [ 'c' => 'grep -i "error" /var/log/syslog', 'd' => 'Search for errors in syslog', 't' => 'review' ],
            [ 'c' => 'grep -i "warning" /var/log/syslog', 'd' => 'Search for warnings in syslog', 't' => 'review' ],
            [ 'c' => 'zgrep -i "error" /var/log/syslog.1.gz', 'd' => 'Search errors in a rotated log', 't' => 'review' ],
            [ 'c' => 'last -n 20', 'd' => 'Review the last 20 logins', 't' => 'review' ],
            [ 'c' => 'lastb -n 20', 'd' => 'Review failed login attempts', 't' => 'review' ],
            [ 'c' => 'ps aux --sort=-%cpu | head -10', 'd' => 'Top 10 CPU consumers', 't' => 'review' ],
            [ 'c' => 'ps aux --sort=-%mem | head -10', 'd' => 'Top 10 memory consumers', 't' => 'review' ],
            [ 'c' => 'ss -tulpn', 'd' => 'Review listening ports', 't' => 'review' ],
            [ 'c' => 'df -hT', 'd' => 'Review disk usage by type', 't' => 'review' ],
            [ 'c' => 'df -h | awk \'$5+0 > 80 {print}\'', 'd' => 'Show disks above 80% usage', 't' => 'review' ],
            [ 'c' => 'free -h && uptime', 'd' => 'Quick resource review', 't' => 'review' ],
            [ 'c' => 'sar -q 1 5', 'd' => 'Review the load average history', 't' => 'review' ],
            [ 'c' => 'iostat -x 1 5', 'd' => 'Review detailed I/O statistics', 't' => 'review' ],
            [ 'c' => 'vmstat 1 5', 'd' => 'Review memory/CPU for 5 samples', 't' => 'review' ],
            [ 'c' => 'nginx -t', 'd' => 'Review the nginx config validity', 't' => 'review' ],
            [ 'c' => 'journalctl -u nginx --since "1 hour ago"', 'd' => 'Review nginx logs for the last hour', 't' => 'review' ],
            [ 'c' => 'cat /var/log/nginx/error.log | tail -50', 'd' => 'Review nginx errors', 't' => 'review' ],
            [ 'c' => 'grep -c " 500 " /var/log/nginx/access.log', 'd' => 'Count HTTP 500 responses', 't' => 'review' ],
            [ 'c' => 'fail2ban-client status sshd', 'd' => 'Review SSH fail2ban jail', 't' => 'review' ],
            [ 'c' => 'lynis audit system --quick', 'd' => 'Run a quick security audit', 't' => 'review' ],
            [ 'c' => 'rkhunter --check --skip-keypress', 'd' => 'Check for rootkits non-interactively', 't' => 'review' ],
            [ 'c' => 'debsums -c', 'd' => 'Verify package checksums (Debian)', 't' => 'review' ],
            [ 'c' => 'rpm -Va', 'd' => 'Verify installed packages (RHEL)', 't' => 'review' ],
            [ 'c' => 'ausearch -m avc --start today', 'd' => 'Review SELinux denials', 't' => 'review' ],
            [ 'c' => 'auditctl -l', 'd' => 'List audit rules', 't' => 'review' ],
            [ 'c' => 'cat /etc/passwd | cut -d: -f1,3', 'd' => 'Review user accounts with UIDs', 't' => 'review' ],
            [ 'c' => 'getent shadow | awk -F: \'$2=="!!" {print $1}\'', 'd' => 'Find users with locked passwords', 't' => 'review' ],
            [ 'c' => 'systemctl list-units --failed', 'd' => 'Review failed systemd units', 't' => 'review' ],
            [ 'c' => 'df -i', 'd' => 'Review inode usage', 't' => 'review' ],
            [ 'c' => 'du -sh /var/log', 'd' => 'Review log directory size', 't' => 'review' ],
            [ 'c' => 'ncdu /', 'd' => 'Interactive disk usage review', 't' => 'review' ],
            [ 'c' => 'lsof | grep deleted', 'd' => 'Find deleted files still in use', 't' => 'review' ],
            [ 'c' => 'ps aux | grep -i defunct', 'd' => 'Review zombie processes', 't' => 'review' ],

            // ---------------- Suggestion ----------------
            [ 'c' => 'history | awk \'{$1="";print}\' | sort | uniq -c | sort -rn | head -20', 'd' => 'Show your most-used commands', 't' => 'suggestion' ],
            [ 'c' => 'history | grep -i sudo', 'd' => 'Find your recent sudo commands', 't' => 'suggestion' ],
            [ 'c' => '!$', 'd' => 'Repeat the last argument of the previous command', 't' => 'suggestion' ],
            [ 'c' => '!!', 'd' => 'Repeat the last command', 't' => 'suggestion' ],
            [ 'c' => 'fc -l -10', 'd' => 'Show the last 10 commands', 't' => 'suggestion' ],
            [ 'c' => 'bind \'\\C-t\':fzf-completion', 'd' => 'Enable fzf completion', 't' => 'suggestion' ],
            [ 'c' => 'ls -la | less', 'd' => 'Scroll through a directory listing', 't' => 'suggestion' ],
            [ 'c' => 'find . -maxdepth 2 -type f | wc -l', 'd' => 'Count files two levels deep', 't' => 'suggestion' ],
            [ 'c' => 'du -ah --max-depth=1 | sort -rh | head -20', 'd' => 'Find the largest files and folders', 't' => 'suggestion' ],
            [ 'c' => 'ps -eo pid,ppid,cmd --sort=-%mem | head', 'd' => 'Show processes by memory', 't' => 'suggestion' ],
            [ 'c' => 'tail -f /var/log/nginx/access.log', 'd' => 'Watch web traffic live', 't' => 'suggestion' ],
            [ 'c' => 'sudo lsof -iTCP -sTCP:LISTEN -P -n', 'd' => 'Show what is listening on each port', 't' => 'suggestion' ],
            [ 'c' => 'ss -s', 'd' => 'Quick network summary', 't' => 'suggestion' ],
            [ 'c' => 'curl -s https://api.ipify.org', 'd' => 'Get your public IP', 't' => 'suggestion' ],
            [ 'c' => 'curl -s http://checkip.amazonaws.com', 'd' => 'Get your public IP (alternative)', 't' => 'suggestion' ],
            [ 'c' => 'dig +short txt ch whoami.cloudflare @1.1.1.1', 'd' => 'Get your public IP via DNS', 't' => 'suggestion' ],
            [ 'c' => 'cat /etc/hostname', 'd' => 'Show the server hostname', 't' => 'suggestion' ],
            [ 'c' => 'neofetch', 'd' => 'Show a system info banner', 't' => 'suggestion' ],
            [ 'c' => 'fastfetch', 'd' => 'Faster system info banner', 't' => 'suggestion' ],
            [ 'c' => 'screenfetch', 'd' => 'Show system info in ASCII art', 't' => 'suggestion' ],
            [ 'c' => 'tldr cmd', 'd' => 'Show simplified man pages for a command', 't' => 'suggestion' ],
            [ 'c' => 'cheat cmd', 'd' => 'Show command cheatsheets', 't' => 'suggestion' ],
            [ 'c' => 'apt list --upgradable', 'd' => 'Show packages that can be upgraded', 't' => 'suggestion' ],
            [ 'c' => 'apt-mark showmanual', 'd' => 'List manually installed packages', 't' => 'suggestion' ],
            [ 'c' => 'nmtui', 'd' => 'Text UI for network configuration', 't' => 'suggestion' ],
            [ 'c' => 'htop -d 5', 'd' => 'htop refreshing every 5 tenths of a second', 't' => 'suggestion' ],
            [ 'c' => 'watch -n 1 "ss -s"', 'd' => 'Watch the network summary live', 't' => 'suggestion' ],
            [ 'c' => 'tree -L 2', 'd' => 'Show the directory tree two levels deep', 't' => 'suggestion' ],
            [ 'c' => 'cd /tmp && curl -LO URL && tar -xzf file', 'd' => 'Download and extract in one step', 't' => 'suggestion' ],
            [ 'c' => 'mkdir -p project/{src,docs,test}', 'd' => 'Create a project structure in one command', 't' => 'suggestion' ],
            [ 'c' => 'echo $PATH | tr ":" "\\n"', 'd' => 'Show each PATH entry on its own line', 't' => 'suggestion' ],
            [ 'c' => 'type -a cmd', 'd' => 'Show all locations of a command', 't' => 'suggestion' ],
            [ 'c' => 'command -v cmd', 'd' => 'Check whether a command exists', 't' => 'suggestion' ],
            [ 'c' => 'grep -rn "TODO" src/', 'd' => 'Find all TODO comments in your code', 't' => 'suggestion' ],
            [ 'c' => 'git log --oneline --graph --all', 'd' => 'Visualize the git history graph', 't' => 'suggestion' ],
            [ 'c' => 'git diff --stat', 'd' => 'Summary of changed files', 't' => 'suggestion' ],
            [ 'c' => 'git log --author="name" --oneline', 'd' => 'Show commits by an author', 't' => 'suggestion' ],
            [ 'c' => 'git log -p --follow -- file', 'd' => 'Show the full history of a file', 't' => 'suggestion' ],
            [ 'c' => 'docker ps --format "table {{.Names}}\\t{{.Status}}"', 'd' => 'Compact container list', 't' => 'suggestion' ],
            [ 'c' => 'docker stats --no-stream', 'd' => 'One-shot container stats', 't' => 'suggestion' ],
            [ 'c' => 'alias please="sudo $(history -p !!)"', 'd' => 'Re-run the last command as root', 't' => 'suggestion' ]
        ];
        foreach ($curated as $c) {
            $addCurated((string) ($c['c'] ?? ''), (string) ($c['d'] ?? ''), (string) ($c['t'] ?? 'useful'));
        }

        $V = fn(string $k, array $arr): array => array_map(fn($v) => ['k' => $k, 'v' => $v], array_values($arr));

        $services = ['nginx', 'apache2', 'mysql', 'postgresql', 'redis-server', 'docker', 'ssh', 'ufw', 'cron', 'networking', 'php8.1-fpm', 'redis', 'elasticsearch', 'rabbitmq-server', 'memcached', 'proftpd', 'vsftpd', 'mongod', 'mysql', 'systemd-journald', 'network-manager', 'bind9', 'fail2ban', 'apache2', 'postgresql'];
        $verbs = ['start', 'stop', 'restart', 'reload', 'status', 'enable', 'disable', 'enable --now', 'is-active', 'is-enabled'];
        $svc = array_values(array_unique($services));;
        $names = ['web', 'db', 'cache', 'app', 'api', 'nginx-proxy', 'redis', 'worker', 'queue', 'mongo', 'frontend', 'backend', 'cron', 'backup', 'bot'];
        $ports = ['22', '53', '80', '443', '3306', '5432', '6379', '8080', '8443', '27017', '11211', '3000', '5000', '9000', '9090', '25', '110', '143', '993', '995', '6667', '1883', '2375', '2376', '9200', '9300', '22122', '8888', '10000', '65535'];
        $packages = ['nginx', 'apache2', 'mysql-server', 'postgresql', 'redis-server', 'docker.io', 'python3-pip', 'nodejs', 'npm', 'git', 'curl', 'wget', 'htop', 'tmux', 'screen', 'ufw', 'fail2ban', 'certbot', 'nginx', 'docker', 'docker-compose', 'nginx-extras', 'build-essential', 'vim', 'rsync', 'logrotate'];
        $users = ['deploy', 'www-data', 'admin', 'worker', 'bot', 'nodejs', 'postgres', 'backup', 'ftp', 'git'];
        $dirs = ['src', 'www', 'uploads', 'data', 'backups', 'logs', 'public', 'media'];
        $files = ['index.js', 'server.js', 'app.js', 'package.json', 'main.py', 'config.yml', 'README.md', 'docker-compose.yml', '.env.example', 'nginx.conf', 'style.css', 'app.py'];
        $hosts = ['example.com', 'api.example.com', 'www.example.com', 'db.example.com', 'web.example.com', '192.168.1.1', '10.0.0.1', 'mail.example.com'];
        $logpaths = ['/var/log/syslog', '/var/log/nginx/error.log', '/var/log/nginx/access.log', '/var/log/apache2/error.log', '/var/log/mysql/error.log', '/var/log/auth.log', '/var/log/messages', '/var/log/docker.log', '/var/log/php8.1-fpm.log', '/var/log/mail.log', '/var/log/fail2ban.log', '/var/log/cron.log', '/var/log/redis/redis-server.log'];
        $branches = ['main', 'dev', 'feature/auth', 'feature/api', 'hotfix/bug', 'release/1.0', 'experiment', 'staging'];
        $images = ['nginx', 'nginx:alpine', 'node', 'node:18-alpine', 'python', 'python:3.12-slim', 'ubuntu', 'alpine', 'redis', 'postgres', 'mysql', 'mongo', 'rabbitmq', 'debian', 'golang', 'openjdk', 'ghost', 'nextcloud', 'portainer', 'traefik'];
        $procs = ['nginx', 'node', 'python', 'mysqld', 'sshd', 'apache2', 'redis-server', 'postgres', 'java', 'php-fpm', 'docker', 'systemd', 'chrome', 'npm', 'git'];

        $templates = [
            // systemctl / service / rc-service families
            [ 'tpl' => '{pm} {verb} {service}', 'desc' => '{verb} the {service} service', 'cat' => 'linux', 'vars' => [$V('pm', ['systemctl', 'service', 'rc-service']), $V('verb', ['start', 'stop', 'restart', 'reload', 'status', 'enable', 'disable', 'restart --force']), $V('service', $svc)] ],
            [ 'tpl' => 'systemctl {verb} {service}.{unit}', 'desc' => '{verb} the {service} {unit}', 'cat' => 'system', 'vars' => [$V('verb', ['start', 'stop', 'restart', 'status', 'enable', 'disable', 'daemon-reload']), $V('service', nh_slice($svc, 0, 16)), $V('unit', ['service', 'timer', 'socket'])] ],
            [ 'tpl' => 'systemctl list-units --type {ut}', 'desc' => 'List {ut} units', 'cat' => 'system', 'vars' => [$V('ut', ['service', 'socket', 'timer', 'path', 'target', 'device', 'mount', 'automount', 'slice', 'scope'])] ],
            [ 'tpl' => 'systemctl list-units --state {st}', 'desc' => 'List units in the {st} state', 'cat' => 'system', 'vars' => [$V('st', ['active', 'inactive', 'failed', 'running', 'exited', 'dead'])] ],
            [ 'tpl' => 'systemctl is-{chk} {service}', 'desc' => 'Check if {service} is {chk}', 'cat' => 'system', 'vars' => [$V('chk', ['active', 'enabled']), $V('service', nh_slice($svc, 0, 20))] ],
            [ 'tpl' => 'systemd-analyze {op}', 'desc' => 'Systemd boot analysis: {op}', 'cat' => 'system', 'vars' => [$V('op', ['blame', 'critical-chain', 'time', 'verify', 'dump'])] ],
            [ 'tpl' => 'journalctl -u {service} --since "{since}"', 'desc' => 'Journal logs for {service} since {since}', 'cat' => 'review', 'vars' => [$V('service', nh_slice($svc, 0, 20)), $V('since', ['today', 'yesterday', '1 hour ago', '30 minutes ago', '1 day ago', 'last week'])] ],
            [ 'tpl' => 'journalctl -u {service} -n {n}', 'desc' => 'Last {n} journal lines for {service}', 'cat' => 'review', 'vars' => [$V('service', nh_slice($svc, 0, 20)), $V('n', ['50', '100', '200', '500'])] ],
            [ 'tpl' => 'journalctl --since "{since}" {sev}', 'desc' => 'Journal entries {since} {sev}', 'cat' => 'review', 'vars' => [$V('since', ['today', 'yesterday', '1 hour ago']), $V('sev', ['-p err', '-p warning', '-p crit', '-p info', '-p debug', ''])] ],

            // Docker families
            [ 'tpl' => 'docker {verb} {name}', 'desc' => 'Docker: {verb} container "{name}"', 'cat' => 'docker', 'vars' => [$V('verb', ['start', 'stop', 'restart', 'rm', 'pause', 'unpause', 'kill', 'top', 'stats', 'inspect', 'logs', 'logs -f', 'port']), $V('name', $names)] ],
            [ 'tpl' => 'docker exec -it {name} {shell}', 'desc' => 'Open {shell} in container "{name}"', 'cat' => 'docker', 'vars' => [$V('name', $names), $V('shell', ['sh', 'bash', 'ash', 'python', 'ls -la'])] ],
            [ 'tpl' => 'docker run -d --name {name} -p {port}:{port} {img}', 'desc' => 'Run {img} mapped to port {port}', 'cat' => 'docker', 'vars' => [$V('name', ['web', 'db', 'cache', 'app', 'api', 'queue', 'frontend', 'backend', 'mongo', 'redis']), $V('port', ['80', '443', '3000', '3306', '5432', '6379', '8080', '27017', '11211', '9000']), $V('img', nh_slice($images, 0, 12))] ],
            [ 'tpl' => 'docker run -d --name {name} {img}', 'desc' => 'Run container "{name}" from {img}', 'cat' => 'docker', 'vars' => [$V('name', nh_slice($names, 0, 10)), $V('img', nh_slice($images, 0, 15))] ],
            [ 'tpl' => 'docker {verb} {img}', 'desc' => 'Docker: {verb} image {img}', 'cat' => 'docker', 'vars' => [$V('verb', ['pull', 'push', 'rmi', 'save -o /tmp/img.tar', 'load -i /tmp/img.tar', 'history', 'tag']), $V('img', $images)] ],
            [ 'tpl' => 'docker cp {name}:/app/{file} ./{file}', 'desc' => 'Copy {file} out of container "{name}"', 'cat' => 'docker', 'vars' => [$V('name', nh_slice($names, 0, 8)), $V('file', ['package.json', 'app.js', 'config.yml', 'logs/app.log', 'public/index.html', '.env'])] ],
            [ 'tpl' => 'docker {verb} --filter "{filter}"', 'desc' => 'Docker {verb} filtered by {filter}', 'cat' => 'docker', 'vars' => [$V('verb', ['ps', 'ps -a', 'images', 'volume ls']), $V('filter', ['status=exited', 'status=created', 'dangling=true', 'label=project=myapp', 'ancestor=nginx'])] ],
            [ 'tpl' => 'docker compose {verb} {name}', 'desc' => 'Compose: {verb} service "{name}"', 'cat' => 'docker', 'vars' => [$V('verb', ['up', 'up -d', 'down', 'start', 'stop', 'restart', 'build', 'pull', 'logs', 'logs -f', 'ps', 'rm']), $V('name', ['', 'web', 'db', 'api', 'worker', 'app', 'redis', 'nginx'])] ],

            // Git families
            [ 'tpl' => 'git {verb} {file}', 'desc' => 'Git: {verb} {file}', 'cat' => 'git', 'vars' => [$V('verb', ['add', 'rm', 'checkout --', 'restore', 'log --oneline --', 'diff --', 'blame', 'show HEAD:', 'commit', 'stash push -m "wip"']), $V('file', $files)] ],
            [ 'tpl' => 'git {verb} {br}', 'desc' => 'Git: {verb} branch "{br}"', 'cat' => 'git', 'vars' => [$V('verb', ['checkout', 'checkout -b', 'merge', 'branch -d', 'push origin', 'pull origin', 'rebase', 'log --oneline origin/']), $V('br', $branches)] ],
            [ 'tpl' => 'git diff {a} {b}', 'desc' => 'Diff between {a} and {b}', 'cat' => 'git', 'vars' => [$V('a', ['main', 'HEAD', 'HEAD~1', 'origin/main', 'stash@{0}', 'v1.0.0']), $V('b', ['HEAD', 'main', 'origin/main', 'dev', 'working tree', 'feature/auth'])] ],
            [ 'tpl' => 'git {verb} --{opt}', 'desc' => 'Git {verb} with --{opt}', 'cat' => 'git', 'vars' => [$V('verb', ['log', 'status', 'branch']), $V('opt', ['oneline', 'graph', 'all', 'stat', 'short', 'decorate', 'name-only', 'numstat', 'first-parent', 'no-merges'])] ],
            [ 'tpl' => 'git tag -a {tag} -m "{msg}"', 'desc' => 'Create tag {tag}: {msg}', 'cat' => 'git', 'vars' => [$V('tag', ['v1.0.0', 'v1.1.0', 'v2.0.0', 'v1.0.1', 'v1.2.0']), $V('msg', ['initial release', 'stable release', 'bugfix release', 'feature release'])] ],

            // Port-based network commands
            [ 'tpl' => 'ss -tlnp | grep {port}', 'desc' => 'Show what listens on port {port}', 'cat' => 'networking', 'vars' => [$V('port', $ports)] ],
            [ 'tpl' => 'lsof -i :{port}', 'desc' => 'Show processes using port {port}', 'cat' => 'networking', 'vars' => [$V('port', $ports)] ],
            [ 'tpl' => 'ufw allow {port}/tcp', 'desc' => 'Allow TCP port {port} in the firewall', 'cat' => 'vps', 'vars' => [$V('port', $ports)] ],
            [ 'tpl' => 'ufw deny {port}/tcp', 'desc' => 'Deny TCP port {port} in the firewall', 'cat' => 'vps', 'vars' => [$V('port', $ports)] ],
            [ 'tpl' => 'iptables -A INPUT -p tcp --dport {port} -j {act}', 'desc' => 'iptables {act} on port {port}', 'cat' => 'networking', 'vars' => [$V('port', $ports), $V('act', ['ACCEPT', 'DROP', 'REJECT'])] ],
            [ 'tpl' => 'nc -zv {host} {port}', 'desc' => 'Test connectivity to {host}:{port}', 'cat' => 'networking', 'vars' => [$V('host', nh_slice($hosts, 0, 6)), $V('port', ['22', '80', '443', '3306', '5432', '6379', '8080', '8443'])] ],
            [ 'tpl' => 'nmap -p {port} {host}', 'desc' => 'Scan port {port} on {host}', 'cat' => 'networking', 'vars' => [$V('port', ['22', '80', '443', '3389', '3306', '5432', '6379', '8080', '27017', '11211', '25', '6667']), $V('host', nh_slice($hosts, 0, 6))] ],
            [ 'tpl' => 'curl -I {host}:{port}', 'desc' => 'Fetch headers from {host}:{port}', 'cat' => 'networking', 'vars' => [$V('host', nh_slice($hosts, 0, 6)), $V('port', ['80', '443', '8080', '8443', '3000', '5000'])] ],
            [ 'tpl' => 'ab -n {n} -c {c} http://{host}/', 'desc' => 'Benchmark {host} ({n} requests, {c} concurrent)', 'cat' => 'vps', 'vars' => [$V('host', ['example.com', 'api.example.com', 'www.example.com']), $V('n', ['100', '500', '1000', '5000']), $V('c', ['1', '10', '50', '100'])] ],
            [ 'tpl' => 'netstat -tulpn | grep {port}', 'desc' => 'Find the process on port {port}', 'cat' => 'networking', 'vars' => [$V('port', $ports)] ],

            // User management
            [ 'tpl' => '{op} {user}', 'desc' => '{op} the "{user}" user', 'cat' => 'linux', 'vars' => [$V('op', ['useradd -m', 'userdel -r', 'passwd', 'deluser', 'deluser --remove-home', 'usermod -aG sudo', 'chage -l', 'faillock --user', 'chfn', 'chsh -s /bin/bash']), $V('user', $users)] ],
            [ 'tpl' => 'su - {user} -c "{cmd}"', 'desc' => 'Run a command as {user}', 'cat' => 'linux', 'vars' => [$V('user', $users), $V('cmd', ['whoami', 'id', 'pwd', 'ls -la ~', 'cd ~ && node -v', 'uptime'])] ],
            [ 'tpl' => 'sudo -u {user} {cmd}', 'desc' => 'Execute {cmd} as {user}', 'cat' => 'linux', 'vars' => [$V('user', $users), $V('cmd', ['whoami', 'id', 'node app.js', 'python3 script.py', 'crontab -l'])] ],
            [ 'tpl' => 'chown -R {user}:{user} /home/{user}', 'desc' => 'Fix ownership for {user}', 'cat' => 'linux', 'vars' => [$V('user', $users)] ],

            // Process management
            [ 'tpl' => 'kill -{sig} {proc}', 'desc' => 'Send {sig} to all "{proc}" processes', 'cat' => 'system', 'vars' => [$V('sig', ['TERM', 'KILL', 'HUP', 'INT', 'STOP', 'CONT']), $V('proc', $procs)] ],
            [ 'tpl' => 'killall {proc}', 'desc' => 'Kill all "{proc}" processes', 'cat' => 'system', 'vars' => [$V('proc', $procs)] ],
            [ 'tpl' => 'pgrep -{opt} {proc}', 'desc' => 'Find "{proc}" processes ({opt})', 'cat' => 'system', 'vars' => [$V('proc', $procs), $V('opt', ['l', 'a', 'af', 'u', 'f'])] ],
            [ 'tpl' => 'pkill -f {pat}', 'desc' => 'Kill processes matching "{pat}"', 'cat' => 'system', 'vars' => [$V('pat', ['node app', 'python main', 'nginx: worker', 'docker-proxy', 'npm run', 'java -jar', 'redis-server', 'mysqld', 'php-fpm', 'sshd:'])] ],
            [ 'tpl' => 'ps aux | grep {proc}', 'desc' => 'Find processes named "{proc}"', 'cat' => 'review', 'vars' => [$V('proc', $procs)] ],
            [ 'tpl' => 'top -b -n {n} | head -{h}', 'desc' => 'Sample the top process list ({n} iterations)', 'cat' => 'system', 'vars' => [$V('n', ['1', '2', '5', '10']), $V('h', ['20', '30', '50'])] ],

            // Cron / automation
            [ 'tpl' => '{sched} {cmd} > /dev/null 2>&1', 'desc' => 'Cron: run {cmd} {sched}', 'cat' => 'auto', 'vars' => [$V('sched', ['*/5 * * * *', '*/10 * * * *', '*/30 * * * *', '0 * * * *', '0 */6 * * *', '0 2 * * *', '0 3 * * *', '30 4 * * *', '0 0 * * 0', '0 9 * * 1-5', '@reboot', '@daily', '@hourly', '@weekly', '@monthly']), $V('cmd', ['/usr/bin/rsync -avz /var/www/ backup@host:/backup', '/backup.sh', 'certbot renew --quiet', 'apt update && apt upgrade -y', 'systemctl restart nginx', '/opt/cleanup.sh', 'docker system prune -f', 'logrotate /etc/logrotate.conf', 'find /tmp -mtime +7 -delete', 'pm2 reload all', 'curl -s -X POST https://api.example.com/ping', '/usr/local/bin/monitor.sh'])] ],
            [ 'tpl' => 'tmux {op} -t {sess}', 'desc' => 'tmux: {op} session "{sess}"', 'cat' => 'auto', 'vars' => [$V('op', ['new', 'attach', 'detach', 'kill', 'kill-window', 'new-window', 'select-window', 'list-panes']), $V('sess', ['dev', 'prod', 'deploy', 'logs', 'monitor', 'worker', 'backup', 'api'])] ],
            [ 'tpl' => 'screen {op} {sess}', 'desc' => 'screen: {op} session "{sess}"', 'cat' => 'auto', 'vars' => [$V('op', ['-S', '-r', '-d', '-ls', '-X quit']), $V('sess', ['dev', 'prod', 'deploy', 'logs', 'monitor', 'worker', 'backup'])] ],
            [ 'tpl' => 'rsync -avz {opts} {src} {dst}', 'desc' => 'Sync {src} to {dst} ({opts})', 'cat' => 'auto', 'vars' => [$V('opts', ['', '--delete', '--exclude "*.log"', '--exclude "node_modules"', '-P', '--dry-run']), $V('src', $dirs), $V('dst', array_merge((array) $dirs, (array) ['/backup', '/media', '/tmp']))] ],

            // File operations
            [ 'tpl' => 'cp -r {src} {dst}', 'desc' => 'Copy {src} to {dst} recursively', 'cat' => 'useful', 'vars' => [$V('src', $dirs), $V('dst', ['backup', 'archive', 'dist', 'public', 'uploads', 'www', 'tmp'])] ],
            [ 'tpl' => 'mv {src} {dst}', 'desc' => 'Move {src} to {dst}', 'cat' => 'useful', 'vars' => [$V('src', array_merge((array) $dirs, (array) nh_slice($files, 0, 4))), $V('dst', ['backup', 'archive', 'dist', 'tmp', 'old', 'media'])] ],
            [ 'tpl' => 'tar -czvf {arch}.tar.gz {src}', 'desc' => 'Archive {src} into {arch}.tar.gz', 'cat' => 'useful', 'vars' => [$V('arch', ['backup', 'release', 'snapshot', 'site', 'logs']), $V('src', array_merge((array) $dirs, (array) ['*.log', '*.conf', 'public_html']))] ],
            [ 'tpl' => 'tar -xzvf {arch}.tar.gz -C {dst}', 'desc' => 'Extract {arch}.tar.gz into {dst}', 'cat' => 'useful', 'vars' => [$V('arch', ['backup', 'release', 'snapshot', 'site', 'logs']), $V('dst', ['/tmp', './restore', '/opt', '/var/www', '~/extract'])] ],
            [ 'tpl' => 'find {dir} -name "{pat}"', 'desc' => 'Find files matching "{pat}" in {dir}', 'cat' => 'useful', 'vars' => [$V('dir', array_merge((array) $dirs, (array) ['/var/log', '/etc', '/opt', '/home'])), $V('pat', ['*.log', '*.conf', '*.php', '*.js', '*.png', '*.json', '*.yml', 'core*', '*.bak', '*.tmp'])] ],
            [ 'tpl' => 'grep -r "{pat}" {dir}', 'desc' => 'Search for "{pat}" in {dir}', 'cat' => 'useful', 'vars' => [$V('pat', ['error', 'warning', 'TODO', 'password', 'panic', 'failed', 'FATAL', 'deprecated', 'timeout', 'denied']), $V('dir', array_merge((array) $dirs, (array) ['/var/log', '/etc', '/opt']))] ],
            [ 'tpl' => 'grep -{fl} "{pat}" {file}', 'desc' => 'Grep {file} for "{pat}" ({fl})', 'cat' => 'review', 'vars' => [$V('fl', ['i', 'n', 'v', 'c', 'l', 'w', 'E', 'A 2', 'B 2', 'C 3', 'o', 'r']), $V('pat', ['error', 'warning', 'refused', '404', '500', 'denied', 'exception', 'timeout', 'panic', 'failed', 'unauthorized', 'backup']), $V('file', ['/var/log/syslog', '/var/log/nginx/error.log', '/var/log/auth.log', 'app.log', 'server.log', 'access.log'])] ],
            [ 'tpl' => 'find {dir} -name "{pat}" -exec {cmd} {{}} \\;', 'desc' => 'Find and {cmd} matching files in {dir}', 'cat' => 'auto', 'vars' => [$V('dir', ['/tmp', './', '/var/log', '/opt/app', '/home/user']), $V('pat', ['*.log', '*.tmp', '*.bak', 'core*', '*.pid']), $V('cmd', ['rm -f', 'ls -la', 'chmod 644', 'cp {} /backup'])] ],
            [ 'tpl' => 'head -n {n} {file}', 'desc' => 'Show the first {n} lines of {file}', 'cat' => 'useful', 'vars' => [$V('n', ['5', '10', '20', '50', '100', '200', '500', '1000']), $V('file', ['/var/log/syslog', '/var/log/nginx/access.log', 'app.log', 'server.log', 'output.txt', 'report.csv'])] ],
            [ 'tpl' => 'tail -n {n} -f {file}', 'desc' => 'Follow the last {n} lines of {file}', 'cat' => 'review', 'vars' => [$V('n', ['10', '20', '50', '100', '200', '500']), $V('file', $logpaths)] ],
            [ 'tpl' => 'awk \'{{print ${fld}}}\' {file}', 'desc' => 'Print field {fld} of each line of {file}', 'cat' => 'auto', 'vars' => [$V('fld', ['1', '2', '3', '4', '5', '7', '9', '11', '$NF']), $V('file', ['/etc/passwd', '/etc/group', 'access.log', 'data.csv', 'output.txt', 'ps_output'])] ],
            [ 'tpl' => 'cut -d{del} -f{fld} {file}', 'desc' => 'Extract field {fld} (delimiter {del}) from {file}', 'cat' => 'auto', 'vars' => [$V('del', [':', ',', ' ', '\\t', '|', '/']), $V('fld', ['1', '2', '3', '4', '6']), $V('file', ['/etc/passwd', 'data.csv', 'access.log', 'output.txt', 'report.csv'])] ],
            [ 'tpl' => 'chmod {mode} {file}', 'desc' => 'Set permissions {mode} on {file}', 'cat' => 'linux', 'vars' => [$V('mode', ['644', '755', '600', '700', '777', '666', '640', '754', '750', '775']), $V('file', array_merge((array) $files, (array) ['script.sh', 'config.conf', 'server.js']))] ],
            [ 'tpl' => 'chown {owner} {file}', 'desc' => 'Set ownership {owner} on {file}', 'cat' => 'linux', 'vars' => [$V('owner', ['root:root', 'www-data:www-data', 'deploy:deploy', 'nobody:nogroup', 'root:www-data', 'git:git']), $V('file', array_merge((array) $files, (array) ['/var/www', '/etc/nginx/nginx.conf']))] ],
            [ 'tpl' => 'ln -s {target} {link}', 'desc' => 'Symlink {link} to {target}', 'cat' => 'linux', 'vars' => [$V('target', ['/usr/bin/python3', '/opt/app/current', '/dev/null', '/etc/nginx/sites-available/site', '/usr/share/zoneinfo/UTC']), $V('link', ['/usr/bin/python', '/opt/app', '/var/log/emptylog', '/etc/nginx/sites-enabled/site', '/etc/localtime'])] ],
            [ 'tpl' => 'sed -i "s/{a}/{b}/g" {file}', 'desc' => 'Replace "{a}" with "{b}" in {file}', 'cat' => 'auto', 'vars' => [$V('a', ['old', 'http://', 'localhost:3000', 'password', 'foo', '\\t']), $V('b', ['new', 'https://', 'localhost:8080', 'secret', 'bar', '  ']), $V('file', nh_slice($files, 0, 6))] ],
            [ 'tpl' => 'wc -{opt} {file}', 'desc' => 'Count {opt} in {file}', 'cat' => 'useful', 'vars' => [$V('opt', ['l', 'w', 'c', 'L', 'm']), $V('file', ['/var/log/syslog', 'access.log', 'data.csv', 'output.txt', 'report.log', 'words.txt'])] ],
            [ 'tpl' => 'sort {opt} {file}', 'desc' => 'Sort {file} ({opt})', 'cat' => 'auto', 'vars' => [$V('opt', ['', '-n', '-r', '-u', '-h', '-t: -k3']), $V('file', ['data.csv', 'output.txt', 'list.txt', 'access.log', 'report.txt', 'ips.txt'])] ],
            [ 'tpl' => 'uniq {opt} {file}', 'desc' => 'Unique lines of {file} ({opt})', 'cat' => 'auto', 'vars' => [$V('opt', ['', '-c', '-d', '-i', '-u']), $V('file', ['ips.txt', 'output.txt', 'words.txt', 'access.log', 'list.txt', 'report.txt'])] ],

            // Mounts / disks
            [ 'tpl' => 'mount /dev/{dev} /{dir}', 'desc' => 'Mount /dev/{dev} to /{dir}', 'cat' => 'linux', 'vars' => [$V('dev', ['sda1', 'sdb1', 'nvme0n1p1', 'vda1', 'xvda1', 'mmcblk0p1', 'sdc1', 'sda2']), $V('dir', ['mnt', 'backup', 'data', 'media', 'exports', 'opt', 'var/lib/docker', 'home'])] ],
            [ 'tpl' => 'umount /{dir}', 'desc' => 'Unmount /{dir}', 'cat' => 'linux', 'vars' => [$V('dir', ['mnt', 'backup', 'data', 'media', 'exports', 'opt'])] ],
            [ 'tpl' => 'mkfs.{fs} /dev/{dev}', 'desc' => 'Create a {fs} filesystem on /dev/{dev}', 'cat' => 'linux', 'vars' => [$V('fs', ['ext4', 'xfs', 'btrfs', 'f2fs', 'vfat']), $V('dev', ['sdb1', 'sdc1', 'nvme0n1p1', 'vda1', 'xvda1', 'sdd1'])] ],
            [ 'tpl' => 'mount --bind {src} {dst}', 'desc' => 'Bind-mount {src} to {dst}', 'cat' => 'system', 'vars' => [$V('src', ['/var/www', '/home/user/data', '/opt/app', '/root', '/srv', '/etc', '/var/log', '/tmp']), $V('dst', ['/var/www', '/home/user/data', '/opt/app', '/root', '/srv', '/etc', '/var/log', '/tmp'])] ],
            [ 'tpl' => 'df -hT | grep {fs}', 'desc' => 'Show disks of type {fs}', 'cat' => 'system', 'vars' => [$V('fs', ['ext4', 'xfs', 'btrfs', 'overlay', 'tmpfs', 'zfs', 'nfs', 'vfat'])] ],

            // SSL / certbot
            [ 'tpl' => 'certbot --nginx -d {domain}', 'desc' => 'Get an SSL cert for {domain}', 'cat' => 'vps', 'vars' => [$V('domain', ['example.com', 'www.example.com', 'api.example.com', 'app.example.com', 'shop.example.com', 'blog.example.com', 'dev.example.com', 'mail.example.com'])] ],
            [ 'tpl' => 'certbot --apache -d {domain}', 'desc' => 'Get an SSL cert for {domain} (Apache)', 'cat' => 'vps', 'vars' => [$V('domain', ['example.com', 'www.example.com', 'api.example.com', 'app.example.com', 'shop.example.com', 'blog.example.com'])] ],
            [ 'tpl' => 'openssl s_client -connect {host}:443 -servername {host}', 'desc' => 'Inspect the SSL cert of {host}', 'cat' => 'networking', 'vars' => [$V('host', ['example.com', 'api.example.com', 'www.example.com', 'shop.example.com', 'blog.example.com', 'mail.example.com'])] ],
            [ 'tpl' => 'openssl req -new -newkey rsa:2048 -nodes -keyout {name}.key -out {name}.csr', 'desc' => 'Generate a CSR ({name})', 'cat' => 'vps', 'vars' => [$V('name', ['example.com', 'api', 'www', 'mail', 'server', 'wildcard'])] ],

            // SSH
            [ 'tpl' => 'ssh -p {port} {user}@{host}', 'desc' => 'SSH to {user}@{host} on port {port}', 'cat' => 'vps', 'vars' => [$V('port', ['22', '2222', '22222', '2022']), $V('user', ['root', 'deploy', 'ubuntu', 'admin', 'www-data', 'git']), $V('host', nh_slice($hosts, 0, 6))] ],
            [ 'tpl' => 'ssh-keygen -t {type} -b {bits}', 'desc' => 'Generate an SSH key ({type}, {bits} bits)', 'cat' => 'vps', 'vars' => [$V('type', ['rsa', 'ed25519', 'ecdsa', 'dsa']), $V('bits', ['2048', '3072', '4096'])] ],
            [ 'tpl' => 'scp {file} {user}@{host}:{dst}', 'desc' => 'Copy {file} to {user}@{host}:{dst}', 'cat' => 'networking', 'vars' => [$V('file', nh_slice($files, 0, 6)), $V('user', ['root', 'deploy', 'ubuntu']), $V('host', nh_slice($hosts, 0, 6)), $V('dst', ['/tmp', '~/', '/var/www', '/opt', '/backup'])] ],
            [ 'tpl' => 'rsync -avz -e "ssh -p {port}" {src}/ {user}@{host}:{dst}/', 'desc' => 'Sync {src} over SSH to {host}', 'cat' => 'auto', 'vars' => [$V('port', ['22', '2222']), $V('src', ['www', 'public', 'backups', 'uploads']), $V('user', ['root', 'deploy', 'ubuntu']), $V('host', nh_slice($hosts, 0, 6)), $V('dst', ['/var/www', '/opt', '/backup', '/home'])] ],
            [ 'tpl' => 'ssh {user}@{host} "command -v {pkg}"', 'desc' => 'Check if {pkg} exists on {host}', 'cat' => 'vps', 'vars' => [$V('user', ['root', 'deploy', 'ubuntu']), $V('host', nh_slice($hosts, 0, 5)), $V('pkg', ['docker', 'nginx', 'node', 'python3', 'git', 'curl', 'pm2'])] ],
            [ 'tpl' => 'ssh -o {opt}={val} {user}@{host}', 'desc' => 'SSH with option {opt}={val}', 'cat' => 'vps', 'vars' => [$V('opt', ['ConnectTimeout', 'StrictHostKeyChecking', 'ServerAliveInterval', 'BatchMode', 'IdentityFile', 'LogLevel', 'ForwardAgent', 'Port']), $V('val', ['10', 'no', '60', 'yes', '~/.ssh/id_ed25519', 'INFO', 'yes', '2222']), $V('user', ['root', 'deploy']), $V('host', ['example.com', '10.0.0.5', 'web.example.com'])] ],

            // Network interfaces
            [ 'tpl' => 'ip addr show {if}', 'desc' => 'Show address config for {if}', 'cat' => 'networking', 'vars' => [$V('if', ['eth0', 'enp0s3', 'enp1s0', 'wlan0', 'tun0', 'docker0'])] ],
            [ 'tpl' => 'ip link set {if} {op}', 'desc' => '{op} interface {if}', 'cat' => 'networking', 'vars' => [$V('if', ['eth0', 'enp0s3', 'enp1s0', 'wlan0', 'tun0']), $V('op', ['up', 'down', 'multicast on', 'promisc on', 'mtu 1400'])] ],
            [ 'tpl' => 'ethtool {if}', 'desc' => 'Show NIC details for {if}', 'cat' => 'networking', 'vars' => [$V('if', ['eth0', 'enp0s3', 'enp1s0', 'ens3', 'ens5', 'wlan0'])] ],
            [ 'tpl' => 'tcpdump -i {if} {filter}', 'desc' => 'Capture traffic on {if} ({filter})', 'cat' => 'networking', 'vars' => [$V('if', ['eth0', 'any', 'lo', 'tun0']), $V('filter', ['port 80', 'port 443', 'port 22', 'icmp', 'udp port 53', 'tcp'])] ],

            // Curl methods
            [ 'tpl' => 'curl -s -X {m} http://{host}/{path}', 'desc' => 'Send a {m} request to {host}/{path}', 'cat' => 'auto', 'vars' => [$V('m', ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS']), $V('host', ['example.com', 'api.example.com', 'localhost:3000', 'localhost:8080', 'web.example.com']), $V('path', ['api/users', 'api/posts', 'api/login', 'health', 'status', 'api/config', 'api/webhook', 'api/upload'])] ],
            [ 'tpl' => 'curl -s -X POST http://{host}/api/{path} -d \'{data}\'', 'desc' => 'POST JSON data to {host}/api/{path}', 'cat' => 'auto', 'vars' => [$V('host', ['localhost:3000', 'localhost:8080', 'api.example.com']), $V('path', ['users', 'login', 'register', 'posts', 'webhook', 'tasks', 'config', 'items']), $V('data', ['{"name":"test"}', '{"username":"admin","password":"***"}', '{"email":"a@b.com"}', '{"title":"hello"}', '{"key":"value"}', '{"action":"deploy"}'])] ],

            // Review: log browsing
            [ 'tpl' => 'tail -n {n} {path}', 'desc' => 'Show the last {n} lines of {path}', 'cat' => 'review', 'vars' => [$V('n', ['20', '50', '100', '200', '500', '1000', '2000']), $V('path', $logpaths)] ],
            [ 'tpl' => 'tail -f {path}', 'desc' => 'Follow {path} live', 'cat' => 'review', 'vars' => [$V('path', $logpaths)] ],
            [ 'tpl' => 'grep -i "{pat}" {path}', 'desc' => 'Search "{pat}" in {path}', 'cat' => 'review', 'vars' => [$V('pat', ['error', 'warning', 'fatal', 'denied', 'refused', 'timeout']), $V('path', $logpaths)] ],
            [ 'tpl' => 'zgrep -i "{pat}" {path}.gz', 'desc' => 'Search "{pat}" in rotated log {path}.gz', 'cat' => 'review', 'vars' => [$V('pat', ['error', 'warning', 'fatal', 'denied']), $V('path', ['/var/log/syslog', '/var/log/auth.log', '/var/log/messages', '/var/log/nginx/access.log'])] ],

            // /proc sweep
            [ 'tpl' => 'cat /proc/{f}', 'desc' => 'Show /proc/{f}', 'cat' => 'system', 'vars' => [$V('f', ['cpuinfo', 'meminfo', 'loadavg', 'uptime', 'stat', 'swaps', 'interrupts', 'partitions', 'version', 'hostname', 'zoneinfo', 'modules'])] ],
            [ 'tpl' => 'watch -n 2 cat /proc/{f}', 'desc' => 'Watch /proc/{f} live', 'cat' => 'system', 'vars' => [$V('f', ['loadavg', 'meminfo', 'stat', 'net/dev'])] ],

            // Package managers
            [ 'tpl' => '{pm} {verb} {pkg}', 'desc' => '{pm}: {verb} {pkg}', 'cat' => 'linux', 'vars' => [$V('pm', ['apt', 'apt-get', 'yum', 'dnf', 'zypper', 'pacman']), $V('verb', ['install -y', 'remove -y', 'purge -y', 'show', 'reinstall -y', 'update']), $V('pkg', ['nginx', 'mysql-server', 'postgresql', 'redis-server', 'docker.io', 'git', 'curl', 'htop', 'tmux', 'vim', 'rsync', 'ufw', 'fail2ban', 'certbot', 'python3-pip', 'nodejs', 'npm', 'build-essential', 'logrotate', 'screen', 'nmap', 'tcpdump', 'sysstat', 'iotop', 'iostat', 'glances'])] ],
            [ 'tpl' => '{pm} {verb}', 'desc' => '{pm}: {verb} all packages', 'cat' => 'linux', 'vars' => [$V('pm', ['apt', 'apt-get', 'yum', 'dnf', 'zypper']), $V('verb', ['update', 'upgrade -y', 'dist-upgrade -y', 'autoremove -y', 'check', 'list --upgradable'])] ],
            [ 'tpl' => 'dpkg -{op} {pkg}', 'desc' => 'dpkg: {op} {pkg}', 'cat' => 'linux', 'vars' => [$V('op', ['l | grep', 's', 'L', 'S', 'c', 'V']), $V('pkg', ['nginx', 'mysql-server', 'openssh-server', 'curl', 'git', 'ufw', 'docker.io', 'python3'])] ],
            [ 'tpl' => 'pacman -{op} {pkg}', 'desc' => 'pacman: -{op} {pkg}', 'cat' => 'linux', 'vars' => [$V('op', ['S', 'Rns', 'Si', 'Ss']), $V('pkg', ['nginx', 'git', 'htop', 'docker', 'nodejs', 'python', 'openssh'])] ],

            // npm / pm2
            [ 'tpl' => 'npm run {script}', 'desc' => 'Run the "{script}" npm script', 'cat' => 'auto', 'vars' => [$V('script', ['dev', 'build', 'start', 'test', 'lint', 'preview', 'deploy', 'migrate'])] ],
            [ 'tpl' => 'npm install {pkg}', 'desc' => 'Install npm package {pkg}', 'cat' => 'auto', 'vars' => [$V('pkg', ['express', 'react', 'next', 'axios', 'dotenv', 'jsonwebtoken', 'nodemon', 'pm2', 'typescript', 'eslint'])] ],
            [ 'tpl' => 'npm {op}', 'desc' => 'npm: {op}', 'cat' => 'auto', 'vars' => [$V('op', ['init -y', 'update', 'outdated', 'list --depth=0', 'audit fix', 'cache clean --force', 'run build', 'ls'])] ],
            [ 'tpl' => 'pm2 {verb} {app}', 'desc' => 'PM2: {verb} app "{app}"', 'cat' => 'vps', 'vars' => [$V('verb', ['start', 'stop', 'restart', 'reload', 'delete', 'logs', 'monit', 'flush', 'describe', 'show']), $V('app', ['app.js', 'server.js', 'index.js', 'api', 'web', 'worker', 'bot', 'cron'])] ],
            [ 'tpl' => 'pm2 {verb} {app} --{opt}', 'desc' => 'PM2 {opt} for {app}', 'cat' => 'vps', 'vars' => [$V('verb', ['start', 'restart']), $V('app', ['app.js', 'server.js', 'index.js', 'api', 'web', 'worker']), $V('opt', ['watch', 'max-memory-restart 200M', 'instances max', 'time', 'no-autorestart'])] ],

            // echo / printf
            [ 'tpl' => 'echo "{str}"', 'desc' => 'Print "{str}"', 'cat' => 'useful', 'vars' => [$V('str', ['Hello, World!', 'Done', 'Starting backup...', 'Server is online', 'All systems go', 'Operation complete', 'Warning: disk almost full', 'Task finished at ' . gmdate('Y-m-d\TH:i:s.v\Z')])] ],
            [ 'tpl' => 'printf "%s\\n" "{str}"', 'desc' => 'Print "{str}" with formatting', 'cat' => 'useful', 'vars' => [$V('str', ['Hello', 'line one', 'line two', 'value: 42', 'status: ok', 'key=value'])] ],

            // dd
            [ 'tpl' => 'dd if=/dev/{src} of={dst} bs={bs} count={count}', 'desc' => 'Copy {count} blocks of {bs} from {src}', 'cat' => 'linux', 'vars' => [$V('src', ['zero', 'urandom', 'sda', 'sdb', 'null']), $V('dst', ['/tmp/file.img', '/dev/null', 'image.img', 'backup.img', 'test.bin']), $V('bs', ['1M', '4M', '8M', '64K', '1K', '512']), $V('count', ['1', '10', '100', '1000', '1024', '2048'])] ],

            // Suggestion tips
            [ 'tpl' => 'history | grep -i {pat}', 'desc' => 'Search your command history for "{pat}"', 'cat' => 'suggestion', 'vars' => [$V('pat', ['docker', 'ssh', 'git', 'apt', 'kill', 'grep', 'tar', 'curl', 'chmod', 'systemctl', 'pm2', 'rsync'])] ],
            [ 'tpl' => 'ls -ltr {dir} | tail -{n}', 'desc' => 'Latest files in {dir}', 'cat' => 'suggestion', 'vars' => [$V('dir', array_merge((array) $dirs, (array) ['/var/log', '/tmp'])), $V('n', ['5', '10', '20'])] ],
            [ 'tpl' => 'du -sh {dir}/* | sort -rh | head -{n}', 'desc' => 'Largest items in {dir}', 'cat' => 'suggestion', 'vars' => [$V('dir', ['/var', '/home', '/opt', '/tmp']), $V('n', ['10', '20'])] ],
            [ 'tpl' => 'ssh {user}@{host} uptime', 'desc' => 'Check uptime on {host} in one shot', 'cat' => 'suggestion', 'vars' => [$V('user', ['root', 'deploy']), $V('host', ['example.com', '10.0.0.5', 'web.example.com', 'db.example.com'])] ],
            [ 'tpl' => 'docker exec {name} sh -c "{cmd}"', 'desc' => 'Run "{cmd}" inside container "{name}"', 'cat' => 'suggestion', 'vars' => [$V('name', nh_slice($names, 0, 10)), $V('cmd', ['whoami', 'df -h', 'free -m', 'uname -a', 'cat /etc/os-release', 'top -bn1 | head -20', 'ls -la', 'printenv'])] ]
        ];
        self::expand($list, $templates);

        // decorate-sort-undecorate with an ICU-ish collation key (mirrors JS localeCompare)
        $keys = [];
        foreach ($list as $i => $c) {
            $keys[$i] = self::collKey((string) $c['cat']) . "\x02" . self::collKey((string) $c['cmd']);
        }
        asort($keys, SORT_STRING);
        $sorted = [];
        foreach ($keys as $i => $k) $sorted[] = $list[$i];
        return self::$cache = $sorted;
    }

    public static function count(): int
    {
        return count(self::all());
    }

    /** Printable ASCII in ICU primary collation order, case-collapsed (generated - see php/tools/gen-commands.mjs). */
    private const COLL_PRIMARY = ' _-,;:!?.\'"()[]{}@*/\\&#%`^+<=>|~$0123456789abcdefghijklmnopqrstuvwxyz';

    private static ?array $collMaps = null;

    /** @return array{0: array<string,string>, 1: array<string,string>} [primary weights, case weights] */
    private static function collMaps(): array
    {
        if (self::$collMaps === null) {
            $primary = [];
            $case = [];
            $order = self::COLL_PRIMARY;
            $len = strlen($order);
            for ($i = 0; $i < $len; $i++) {
                $c = $order[$i];
                $primary[$c] = chr(33 + $i);
                $case[$c] = '0';
                $upper = strtoupper($c);
                if ($upper !== $c) {
                    $primary[$upper] = chr(33 + $i);
                    $case[$upper] = '1';
                }
            }
            self::$collMaps = [$primary, $case];
        }
        return self::$collMaps;
    }

    /** Sort key mirroring JS localeCompare: ICU-ish primary weights, lowercase before uppercase. */
    private static function collKey(string $str): string
    {
        [$primary, $case] = self::collMaps();
        return strtr($str, $primary) . "\x01" . strtr($str, $case);
    }

    /** @return array<string,int> command count per category */
    public static function categoryCounts(): array
    {
        $counts = [];
        foreach (self::CATEGORIES as $key => $label) $counts[$key] = 0;
        foreach (self::all() as $c) $counts[$c['cat']] = ($counts[$c['cat']] ?? 0) + 1;
        return $counts;
    }

    private static function expand(array &$list, array $templates): void
    {
        foreach ($templates as $t) {
            $combos = [[]];
            foreach (($t['vars'] ?? []) as $vals) {
                $next = [];
                foreach ($combos as $combo) {
                    foreach ($vals as $v) $next[] = array_merge($combo, [$v['k'] => $v['v']]);
                }
                $combos = $next;
            }
            foreach ($combos as $c) {
                $cmd = (string) $t['tpl'];
                $desc = (string) ($t['desc'] ?? '');
                foreach (array_keys($c) as $k) {
                    $cmd = str_replace('{' . $k . '}', (string) $c[$k], $cmd);
                    $desc = str_replace('{' . $k . '}', (string) $c[$k], $desc);
                }
                if ($cmd !== '') $list[] = ['cmd' => $cmd, 'desc' => $desc, 'cat' => (string) $t['cat']];
            }
        }
    }
}
