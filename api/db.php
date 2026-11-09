<?php
// Shared database helper. Also creates the admin tables the first time it runs
// and fills them with the content that is currently on your website.
require_once __DIR__ . '/config.php';

const ICON_KEYS = ['globe', 'layout', 'terminal', 'palette', 'pen', 'network', 'database', 'video', 'code', 'java', 'layers', 'folder'];
const CATEGORIES = ['web' => 'Web', 'python' => 'Python', 'design' => 'Design', 'art' => 'Art', 'network' => 'Networking', 'data' => 'Data'];

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

function ensure_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY idx_created (created_at), KEY idx_ip_created (ip_address, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        username VARCHAR(40) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ip VARCHAR(45) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY idx_ip_time (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(60) NOT NULL,
        setting_value TEXT NOT NULL,
        PRIMARY KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(150) NOT NULL,
        category VARCHAR(100) NOT NULL DEFAULT '',
        description VARCHAR(500) NOT NULL DEFAULT '',
        tags VARCHAR(200) NOT NULL DEFAULT '',
        link VARCHAR(255) NOT NULL DEFAULT '',
        icon VARCHAR(20) NOT NULL DEFAULT 'folder',
        image VARCHAR(255) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        visible TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS skills (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kind ENUM('skill','tool') NOT NULL DEFAULT 'skill',
        name VARCHAR(100) NOT NULL,
        percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
        icon VARCHAR(20) NOT NULL DEFAULT 'code',
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS education (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        period VARCHAR(60) NOT NULL DEFAULT '',
        title VARCHAR(150) NOT NULL,
        description VARCHAR(300) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    seed_defaults($pdo);
}

function table_is_empty(PDO $pdo, string $table): bool {
    // $table only ever comes from the fixed list below, never from user input
    return (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() === 0;
}

function seed_defaults(PDO $pdo): void {
    // Default admin account (stored as a bcrypt hash, not plain text).
    // Username: dr_hernandez  |  change the password later in Admin > Account.
    if (table_is_empty($pdo, 'admin_users')) {
        $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')
            ->execute(['dr_hernandez', '$2b$12$REVo6LLEBO9XWwocIRqQw.PtQ7LG9kKxf84S9RI5Ain8veJ0D7JgO']);
    }

    if (table_is_empty($pdo, 'site_settings')) {
        $settings = [
            'full_name'          => 'Dennise Hernandez',
            'footer_tagline'     => 'IT Student · Web · Networking · Design',
            'hero_eyebrow'       => 'Information Technology Student',
            'hero_headline'      => "Hi, I'm Dennise —\nI build *websites* & designs.",
            'hero_text'          => 'Second-year IT student at Pamantasan ng Lungsod ng Valenzuela, learning web development, networking and data — and expressing my creativity through design and art.',
            'home_about_heading' => 'Learning by building',
            'home_about_text'    => "I'm enhancing my skills in HTML, CSS and Bootstrap to create clean, functional websites. I enjoy the mix of design and code — it lets me explore my creativity while sharpening my technical skills. My goal is to become an IT professional who builds effective, functional websites and designs.",
            'about_heading'      => "Hi, I'm Dennise!",
            'about_p1'           => "I'm a second-year Information Technology student at **Pamantasan ng Lungsod ng Valenzuela**. I'm currently learning web development and enjoy creating websites with HTML, CSS and Bootstrap.",
            'about_p2'           => "I'm exploring my creativity through design, which in turn helps me improve my coding. My goal is to become an IT professional who can build effective and functional websites and designs.",
            'email'              => 'dennisereignh@gmail.com',
            'phone'              => '+63 991 972 7596',
            'facebook_url'       => 'https://www.facebook.com/ottooooo13',
            'facebook_label'     => 'Dennise Hernandez',
            'instagram_url'      => 'https://www.instagram.com/ottttooooo_1/',
            'instagram_label'    => '@ottttooooo_1',
            'profile_photo'      => '',
        ];
        $st = $pdo->prepare('INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
        foreach ($settings as $k => $v) { $st->execute([$k, $v]); }
    }

    if (table_is_empty($pdo, 'projects')) {
        $rows = [
            ['HTML + CSS', 'web', 'Practices for creating websites, including an origami tutorial site and school projects.', 'HTML, CSS', 'web.html', 'globe'],
            ['Bootstrap', 'web', 'Responsive website layouts built with the Bootstrap framework.', 'Bootstrap, Responsive', 'bootstrap.html', 'layout'],
            ['Python', 'python', 'Programming practice and GUI programs made as school activities.', 'Python, GUI', 'python.html', 'terminal'],
            ['Multimedia Design', 'design', 'Posters, logos, text-masking and mock-up presentations made in Photoshop and Canva.', 'Photoshop, Canva, Branding', 'multi.html', 'palette'],
            ['Artworks & Sketches', 'art', 'Hand-drawn sketches and illustrations made in my free time — a reflection of my creative side.', 'Drawing, Illustration', 'artwork.html', 'pen'],
            ['Cisco Packet Tracer', 'network', 'Network topologies from my first-semester networking course: routers, switches, subnets and WANs.', 'Networking, Cisco, IP addressing', 'cisco.html', 'network'],
            ['Information Management', 'data', 'CodeSignal certificates in SQL, Python data analysis, statistics and data visualization.', 'SQL, Python, Data Analysis', 'im.html', 'database'],
            ['Demo Reel', 'design web', 'A video showcase of my work, put together as a quick overview.', 'Video', 'demo.html', 'video'],
        ];
        $st = $pdo->prepare('INSERT INTO projects (title, category, description, tags, link, icon, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($rows as $i => $r) { $st->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $i + 1]); }
    }

    if (table_is_empty($pdo, 'skills')) {
        $rows = [
            ['skill', 'HTML & CSS', 75, 'code'],
            ['skill', 'SQL & database basics', 70, 'database'],
            ['skill', 'Cisco / networking', 65, 'network'],
            ['skill', 'Java', 55, 'java'],
            ['skill', 'Photoshop & Canva', 50, 'layers'],
            ['tool', 'HTML & CSS', 0, 'code'],
            ['tool', 'Bootstrap', 0, 'layout'],
            ['tool', 'Java', 0, 'java'],
            ['tool', 'SQL', 0, 'database'],
            ['tool', 'Python', 0, 'terminal'],
            ['tool', 'Cisco Packet Tracer', 0, 'network'],
            ['tool', 'Drawing & illustration', 0, 'pen'],
            ['tool', 'Photoshop', 0, 'layers'],
            ['tool', 'Canva', 0, 'palette'],
        ];
        $st = $pdo->prepare('INSERT INTO skills (kind, name, percent, icon, sort_order) VALUES (?, ?, ?, ?, ?)');
        foreach ($rows as $i => $r) { $st->execute([$r[0], $r[1], $r[2], $r[3], $i + 1]); }
    }

    if (table_is_empty($pdo, 'education')) {
        $rows = [
            ['PRESENT', 'BS Information Technology', 'Pamantasan ng Lungsod ng Valenzuela — Second year'],
            ['2022 – 2024', 'Senior High School (STEM)', 'Valenzuela National High School — Science, Technology, Engineering & Mathematics'],
            ['2010 – 2016', 'Elementary', 'Doña Ata Elementary School'],
        ];
        $st = $pdo->prepare('INSERT INTO education (period, title, description, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($rows as $i => $r) { $st->execute([$r[0], $r[1], $r[2], $i + 1]); }
    }
}
