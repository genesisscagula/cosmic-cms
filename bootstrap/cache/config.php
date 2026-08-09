<?php return array (
  'broadcasting' => 
  array (
    'default' => 'log',
    'connections' => 
    array (
      'reverb' => 
      array (
        'driver' => 'reverb',
        'key' => NULL,
        'secret' => NULL,
        'app_id' => NULL,
        'options' => 
        array (
          'host' => NULL,
          'port' => 443,
          'scheme' => 'https',
          'useTLS' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'pusher' => 
      array (
        'driver' => 'pusher',
        'key' => NULL,
        'secret' => NULL,
        'app_id' => NULL,
        'options' => 
        array (
          'cluster' => NULL,
          'host' => 'api-mt1.pusher.com',
          'port' => 443,
          'scheme' => 'https',
          'encrypted' => true,
          'useTLS' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'ably' => 
      array (
        'driver' => 'ably',
        'key' => NULL,
      ),
      'log' => 
      array (
        'driver' => 'log',
      ),
      'null' => 
      array (
        'driver' => 'null',
      ),
    ),
  ),
  'concurrency' => 
  array (
    'default' => 'process',
  ),
  'cors' => 
  array (
    'paths' => 
    array (
      0 => 'api/*',
      1 => 'sanctum/csrf-cookie',
    ),
    'allowed_methods' => 
    array (
      0 => '*',
    ),
    'allowed_origins' => 
    array (
      0 => '*',
    ),
    'allowed_origins_patterns' => 
    array (
    ),
    'allowed_headers' => 
    array (
      0 => '*',
    ),
    'exposed_headers' => 
    array (
    ),
    'max_age' => 0,
    'supports_credentials' => false,
  ),
  'hashing' => 
  array (
    'driver' => 'bcrypt',
    'bcrypt' => 
    array (
      'rounds' => '12',
      'verify' => true,
      'limit' => NULL,
    ),
    'argon' => 
    array (
      'memory' => 65536,
      'threads' => 1,
      'time' => 4,
      'verify' => true,
    ),
    'rehash_on_login' => true,
  ),
  'app' => 
  array (
    'name' => 'Laravel',
    'env' => 'local',
    'debug' => true,
    'url' => 'http://127.0.0.1:8000',
    'frontend_url' => 'http://localhost:3000',
    'asset_url' => NULL,
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'cipher' => 'AES-256-CBC',
    'key' => 'base64:Qvry2S5fGjHfy6Hj6f/FbU7vpW7FoD+7KbSc/owHqoQ=',
    'previous_keys' => 
    array (
    ),
    'maintenance' => 
    array (
      'driver' => 'file',
      'store' => 'database',
    ),
    'providers' => 
    array (
      0 => 'Illuminate\\Auth\\AuthServiceProvider',
      1 => 'Illuminate\\Broadcasting\\BroadcastServiceProvider',
      2 => 'Illuminate\\Bus\\BusServiceProvider',
      3 => 'Illuminate\\Cache\\CacheServiceProvider',
      4 => 'Illuminate\\Foundation\\Providers\\ConsoleSupportServiceProvider',
      5 => 'Illuminate\\Concurrency\\ConcurrencyServiceProvider',
      6 => 'Illuminate\\Cookie\\CookieServiceProvider',
      7 => 'Illuminate\\Database\\DatabaseServiceProvider',
      8 => 'Illuminate\\Encryption\\EncryptionServiceProvider',
      9 => 'Illuminate\\Filesystem\\FilesystemServiceProvider',
      10 => 'Illuminate\\Foundation\\Providers\\FoundationServiceProvider',
      11 => 'Illuminate\\Hashing\\HashServiceProvider',
      12 => 'Illuminate\\Mail\\MailServiceProvider',
      13 => 'Illuminate\\Notifications\\NotificationServiceProvider',
      14 => 'Illuminate\\Pagination\\PaginationServiceProvider',
      15 => 'Illuminate\\Auth\\Passwords\\PasswordResetServiceProvider',
      16 => 'Illuminate\\Pipeline\\PipelineServiceProvider',
      17 => 'Illuminate\\Queue\\QueueServiceProvider',
      18 => 'Illuminate\\Redis\\RedisServiceProvider',
      19 => 'Illuminate\\Session\\SessionServiceProvider',
      20 => 'Illuminate\\Translation\\TranslationServiceProvider',
      21 => 'Illuminate\\Validation\\ValidationServiceProvider',
      22 => 'Illuminate\\View\\ViewServiceProvider',
      23 => 'App\\Providers\\AppServiceProvider',
    ),
    'aliases' => 
    array (
      'App' => 'Illuminate\\Support\\Facades\\App',
      'Arr' => 'Illuminate\\Support\\Arr',
      'Artisan' => 'Illuminate\\Support\\Facades\\Artisan',
      'Auth' => 'Illuminate\\Support\\Facades\\Auth',
      'Benchmark' => 'Illuminate\\Support\\Benchmark',
      'Blade' => 'Illuminate\\Support\\Facades\\Blade',
      'Broadcast' => 'Illuminate\\Support\\Facades\\Broadcast',
      'Bus' => 'Illuminate\\Support\\Facades\\Bus',
      'Cache' => 'Illuminate\\Support\\Facades\\Cache',
      'Concurrency' => 'Illuminate\\Support\\Facades\\Concurrency',
      'Config' => 'Illuminate\\Support\\Facades\\Config',
      'Context' => 'Illuminate\\Support\\Facades\\Context',
      'Cookie' => 'Illuminate\\Support\\Facades\\Cookie',
      'Crypt' => 'Illuminate\\Support\\Facades\\Crypt',
      'Date' => 'Illuminate\\Support\\Facades\\Date',
      'DB' => 'Illuminate\\Support\\Facades\\DB',
      'Eloquent' => 'Illuminate\\Database\\Eloquent\\Model',
      'Event' => 'Illuminate\\Support\\Facades\\Event',
      'File' => 'Illuminate\\Support\\Facades\\File',
      'Gate' => 'Illuminate\\Support\\Facades\\Gate',
      'Hash' => 'Illuminate\\Support\\Facades\\Hash',
      'Http' => 'Illuminate\\Support\\Facades\\Http',
      'Js' => 'Illuminate\\Support\\Js',
      'Lang' => 'Illuminate\\Support\\Facades\\Lang',
      'Log' => 'Illuminate\\Support\\Facades\\Log',
      'Mail' => 'Illuminate\\Support\\Facades\\Mail',
      'Notification' => 'Illuminate\\Support\\Facades\\Notification',
      'Number' => 'Illuminate\\Support\\Number',
      'Password' => 'Illuminate\\Support\\Facades\\Password',
      'Process' => 'Illuminate\\Support\\Facades\\Process',
      'Queue' => 'Illuminate\\Support\\Facades\\Queue',
      'RateLimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
      'Redirect' => 'Illuminate\\Support\\Facades\\Redirect',
      'Request' => 'Illuminate\\Support\\Facades\\Request',
      'Response' => 'Illuminate\\Support\\Facades\\Response',
      'Route' => 'Illuminate\\Support\\Facades\\Route',
      'Schedule' => 'Illuminate\\Support\\Facades\\Schedule',
      'Schema' => 'Illuminate\\Support\\Facades\\Schema',
      'Session' => 'Illuminate\\Support\\Facades\\Session',
      'Storage' => 'Illuminate\\Support\\Facades\\Storage',
      'Str' => 'Illuminate\\Support\\Str',
      'Uri' => 'Illuminate\\Support\\Uri',
      'URL' => 'Illuminate\\Support\\Facades\\URL',
      'Validator' => 'Illuminate\\Support\\Facades\\Validator',
      'View' => 'Illuminate\\Support\\Facades\\View',
      'Vite' => 'Illuminate\\Support\\Facades\\Vite',
    ),
  ),
  'auth' => 
  array (
    'defaults' => 
    array (
      'guard' => 'web',
      'passwords' => 'users',
    ),
    'guards' => 
    array (
      'web' => 
      array (
        'driver' => 'session',
        'provider' => 'users',
      ),
      'sanctum' => 
      array (
        'driver' => 'sanctum',
        'provider' => NULL,
      ),
    ),
    'providers' => 
    array (
      'users' => 
      array (
        'driver' => 'eloquent',
        'model' => 'App\\Models\\User',
      ),
    ),
    'passwords' => 
    array (
      'users' => 
      array (
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
      ),
    ),
    'password_timeout' => 10800,
  ),
  'cache' => 
  array (
    'default' => 'database',
    'stores' => 
    array (
      'array' => 
      array (
        'driver' => 'array',
        'serialize' => false,
      ),
      'session' => 
      array (
        'driver' => 'session',
        'key' => '_cache',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'cache',
        'lock_connection' => NULL,
        'lock_table' => NULL,
      ),
      'file' => 
      array (
        'driver' => 'file',
        'path' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\framework/cache/data',
        'lock_path' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\framework/cache/data',
      ),
      'memcached' => 
      array (
        'driver' => 'memcached',
        'persistent_id' => NULL,
        'sasl' => 
        array (
          0 => NULL,
          1 => NULL,
        ),
        'options' => 
        array (
        ),
        'servers' => 
        array (
          0 => 
          array (
            'host' => '127.0.0.1',
            'port' => 11211,
            'weight' => 100,
          ),
        ),
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
      ),
      'dynamodb' => 
      array (
        'driver' => 'dynamodb',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'table' => 'cache',
        'endpoint' => NULL,
      ),
      'octane' => 
      array (
        'driver' => 'octane',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'stores' => 
        array (
          0 => 'database',
          1 => 'array',
        ),
      ),
    ),
    'prefix' => 'laravel-cache-',
  ),
  'cosmic' => 
  array (
    'platform_owner_email' => 'genesisscagula@gmail.com',
    'platform_owner_plan' => 'agency_pro',
    'agency_workspace_name' => 'CosmicReact',
    'trial_website_id' => 14,
  ),
  'cosmic-backup' => 
  array (
    'enabled' => true,
    'disk' => 'local',
    'path' => 'backups/cosmic',
    'database' => 
    array (
      'enabled' => true,
      'connection' => 'sqlite',
      'mysqldump_binary' => 'mysqldump',
      'pg_dump_binary' => 'pg_dump',
    ),
    'files' => 
    array (
      'enabled' => true,
      'paths' => 
      array (
        0 => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\app/public',
      ),
    ),
    'retention' => 
    array (
      'daily_days' => 7,
      'weekly_weeks' => 4,
      'monthly_months' => 6,
    ),
    'health' => 
    array (
      'maximum_age_hours' => 30,
      'minimum_bytes' => 1024,
    ),
  ),
  'cosmic-billing-qa' => 
  array (
    'stale_pending_hours' => 24,
    'stuck_webhook_minutes' => 15,
    'failed_webhook_warning_count' => 1,
    'schedule_daily_at' => '09:15',
  ),
  'cosmic-chat' => 
  array (
    'enabled' => true,
    'model' => 'gpt-5.6-luna',
    'max_user_chars' => 1000,
    'history_messages' => 12,
    'welcome' => 'Hi! I’m the Cosmic CMS assistant. Ask me about building a website, plans, features, or the free trial.',
    'unknown_reply' => 'I don’t have a verified Cosmic CMS answer for that yet. I can keep your question in this chat for the Cosmic CMS team to review.',
  ),
  'cosmic-chat-knowledge' => 
  array (
    'version' => '1.2',
    'product' => 
    array (
      'name' => 'Cosmic CMS',
      'website' => 'https://www.cosmiccms.com',
      'start_url' => 'https://www.cosmiccms.com/start',
      'pricing_url' => 'https://www.cosmiccms.com/pricing',
      'summary' => 'Cosmic CMS is an AI-assisted website builder that creates an editable business website starting point from a prompt.',
    ),
    'verified_facts' => 
    array (
      0 => 'Generated websites remain editable in the Cosmic CMS visual builder.',
      1 => 'Cosmic CMS uses reusable website sections called Sparks.',
      2 => 'The builder supports website-wide theme customization plus header and footer editing when the active plan permits those capabilities.',
      3 => 'Cosmic CMS supports contact forms on the personal plans currently configured in the application.',
      4 => 'Personal plan capabilities and limits differ by tier; use the live plan data supplied in this knowledge context.',
      5 => 'Static HTML export is enabled on the currently configured Starter, Growth, and Pro personal plans.',
      6 => 'The Pro personal plan currently includes custom-domain capability; do not imply custom-domain support for another tier unless the live plan data says so.',
      7 => 'Agency plans are separate from personal plans and have their own website, template, Spark, analytics, leads, team, and agency-tool limits.',
      8 => 'The public trial begins at /start and uses Guest Cosmic Credits for supported trial customization actions.',
    ),
    'terminology' => 
    array (
      'Sparks' => 'Reusable website sections/layouts available in Cosmic CMS.',
      'Guest Cosmic Credits' => 'Credits assigned to the public trial for supported customization actions before signup.',
      'Owned Sparks' => 'Sparks attached to a customer account/library under the applicable plan rules.',
    ),
    'answer_boundaries' => 
    array (
      0 => 'Do not promise a ranking position, traffic result, lead volume, revenue result, or delivery timeline.',
      1 => 'Do not invent discounts, refunds, promotional offers, integrations, hosting terms, domain-registration terms, or support response times.',
      2 => 'Do not claim a feature is available on a plan unless the supplied live capability data supports it.',
      3 => 'For account-specific billing, payment failures, private account data, or a request requiring human review, say the Cosmic CMS team needs to review it.',
    ),
  ),
  'cosmic-industries' => 
  array (
    'default' => 
    array (
      'label' => 'General Business',
      'query' => 'professional small business website photography',
      'aliases' => 
      array (
        0 => 'general business',
        1 => 'business',
        2 => 'company',
      ),
    ),
    'automotive' => 
    array (
      'label' => 'Automotive',
      'query' => 'professional automotive workshop mechanic vehicle service',
      'aliases' => 
      array (
        0 => 'auto repair',
        1 => 'car repair',
        2 => 'mechanic',
        3 => 'garage',
        4 => 'dealership',
        5 => 'vehicle service',
      ),
    ),
    'bakery' => 
    array (
      'label' => 'Bakery',
      'query' => 'artisan bakery bread pastry shop professional photography',
      'aliases' => 
      array (
        0 => 'baker',
        1 => 'pastry',
        2 => 'bread shop',
        3 => 'cake shop',
      ),
    ),
    'cleaning' => 
    array (
      'label' => 'Cleaning Services',
      'query' => 'professional residential commercial cleaning service team',
      'aliases' => 
      array (
        0 => 'house cleaning',
        1 => 'commercial cleaning',
        2 => 'janitorial',
        3 => 'maid service',
      ),
    ),
    'coffee' => 
    array (
      'label' => 'Coffee Shop',
      'query' => 'specialty coffee shop cafe barista interior professional photography',
      'aliases' => 
      array (
        0 => 'coffee shop',
        1 => 'coffeehouse',
        2 => 'cafe',
        3 => 'café',
        4 => 'specialty coffee',
        5 => 'coffee roastery',
        6 => 'roastery',
      ),
    ),
    'construction' => 
    array (
      'label' => 'Construction',
      'query' => 'commercial construction contractor building site professional photography',
      'aliases' => 
      array (
        0 => 'contractor',
        1 => 'builder',
        2 => 'home builder',
        3 => 'renovation',
        4 => 'remodeling',
        5 => 'remodelling',
      ),
    ),
    'dentist' => 
    array (
      'label' => 'Dental Clinic',
      'query' => 'modern dental clinic dentist patient care professional photography',
      'aliases' => 
      array (
        0 => 'dental',
        1 => 'dental clinic',
        2 => 'dentistry',
        3 => 'orthodontist',
        4 => 'orthodontic',
      ),
    ),
    'education' => 
    array (
      'label' => 'Education',
      'query' => 'modern school academy students learning classroom professional photography',
      'aliases' => 
      array (
        0 => 'school',
        1 => 'academy',
        2 => 'tutoring',
        3 => 'training center',
        4 => 'online course',
      ),
    ),
    'electrician' => 
    array (
      'label' => 'Electrician',
      'query' => 'professional electrician electrical installation technician at work',
      'aliases' => 
      array (
        0 => 'electrical',
        1 => 'electrical service',
        2 => 'wiring',
      ),
    ),
    'ev-charging' => 
    array (
      'label' => 'EV Charging',
      'query' => 'electric vehicle charging station installer commercial residential',
      'aliases' => 
      array (
        0 => 'ev charging',
        1 => 'electric vehicle charging',
        2 => 'charging station installer',
      ),
    ),
    'finance' => 
    array (
      'label' => 'Finance',
      'query' => 'professional financial advisor accounting office client meeting',
      'aliases' => 
      array (
        0 => 'financial advisor',
        1 => 'accounting',
        2 => 'accountant',
        3 => 'bookkeeping',
        4 => 'wealth management',
      ),
    ),
    'fitness' => 
    array (
      'label' => 'Fitness',
      'query' => 'premium fitness gym training workout professional photography',
      'aliases' => 
      array (
        0 => 'fitness studio',
        1 => 'gym',
        2 => 'personal trainer',
        3 => 'personal training',
        4 => 'workout',
        5 => 'crossfit',
        6 => 'yoga studio',
      ),
    ),
    'hotel' => 
    array (
      'label' => 'Hotel & Resort',
      'query' => 'luxury boutique hotel resort hospitality professional photography',
      'aliases' => 
      array (
        0 => 'resort',
        1 => 'hotel and resort',
        2 => 'accommodation',
        3 => 'lodging',
        4 => 'boutique hotel',
        5 => 'hospitality',
      ),
    ),
    'landscaping' => 
    array (
      'label' => 'Landscaping',
      'query' => 'professional landscaping garden design lawn care completed project',
      'aliases' => 
      array (
        0 => 'landscape',
        1 => 'lawn care',
        2 => 'garden design',
        3 => 'tree service',
      ),
    ),
    'lawyer' => 
    array (
      'label' => 'Law Firm',
      'query' => 'professional law firm attorney office client consultation',
      'aliases' => 
      array (
        0 => 'law firm',
        1 => 'attorney',
        2 => 'legal',
        3 => 'legal services',
        4 => 'legal counsel',
      ),
    ),
    'marine-repair' => 
    array (
      'label' => 'Marine Engine Repair',
      'query' => 'marine engine repair workshop outboard motor mechanic boat maintenance',
      'aliases' => 
      array (
        0 => 'marine engine repair',
        1 => 'boat engine repair',
        2 => 'outboard repair',
        3 => 'marine mechanic',
        4 => 'marine repair company',
      ),
    ),
    'medical' => 
    array (
      'label' => 'Medical Clinic',
      'query' => 'modern medical clinic doctor patient care professional photography',
      'aliases' => 
      array (
        0 => 'medical clinic',
        1 => 'clinic',
        2 => 'healthcare',
        3 => 'health care',
        4 => 'doctor',
        5 => 'physician',
        6 => 'wellness center',
      ),
    ),
    'pet-services' => 
    array (
      'label' => 'Pet Services',
      'query' => 'professional pet care veterinary grooming service animals',
      'aliases' => 
      array (
        0 => 'pet care',
        1 => 'pet grooming',
        2 => 'veterinary',
        3 => 'pet cremation',
        4 => 'animal services',
      ),
    ),
    'plumbing' => 
    array (
      'label' => 'Plumbing',
      'query' => 'professional plumber plumbing repair technician at work',
      'aliases' => 
      array (
        0 => 'plumber',
        1 => 'drain cleaning',
        2 => 'water heater',
        3 => 'pipe repair',
      ),
    ),
    'real-estate' => 
    array (
      'label' => 'Real Estate',
      'query' => 'premium real estate architecture property listing professional photography',
      'aliases' => 
      array (
        0 => 'real estate',
        1 => 'realtor',
        2 => 'realty',
        3 => 'property listing',
        4 => 'property management',
        5 => 'property development',
      ),
    ),
    'restaurant' => 
    array (
      'label' => 'Restaurant',
      'query' => 'premium restaurant dining food hospitality professional photography',
      'aliases' => 
      array (
        0 => 'dining',
        1 => 'bistro',
        2 => 'food service',
        3 => 'pizza restaurant',
        4 => 'catering',
      ),
    ),
    'roofing' => 
    array (
      'label' => 'Roofing',
      'query' => 'professional roofing contractor roof installation repair',
      'aliases' => 
      array (
        0 => 'roofer',
        1 => 'roof repair',
        2 => 'roof replacement',
      ),
    ),
    'salon' => 
    array (
      'label' => 'Salon & Beauty',
      'query' => 'premium beauty salon hair stylist spa professional photography',
      'aliases' => 
      array (
        0 => 'beauty salon',
        1 => 'beauty',
        2 => 'hair stylist',
        3 => 'barber',
        4 => 'spa',
        5 => 'nail studio',
      ),
    ),
    'technology' => 
    array (
      'label' => 'Technology',
      'query' => 'modern technology software team office professional photography',
      'aliases' => 
      array (
        0 => 'software',
        1 => 'saas',
        2 => 'tech startup',
        3 => 'it services',
        4 => 'web development',
        5 => 'digital agency',
      ),
    ),
    'travel' => 
    array (
      'label' => 'Travel',
      'query' => 'luxury travel destination tour hospitality professional photography',
      'aliases' => 
      array (
        0 => 'travel agency',
        1 => 'tour',
        2 => 'tourism',
        3 => 'vacation',
        4 => 'holiday',
        5 => 'destination',
        6 => 'yacht',
        7 => 'charter',
        8 => 'cruise',
        9 => 'sailing',
      ),
    ),
  ),
  'cosmic-launch' => 
  array (
    'production_url' => 'http://127.0.0.1:8000',
    'require_https' => true,
    'require_queue' => true,
    'require_mail' => true,
    'require_paypal' => true,
    'minimum_php_version' => '8.2.0',
    'minimum_free_disk_mb' => 1024,
    'required_extensions' => 
    array (
      0 => 'curl',
      1 => 'json',
      2 => 'mbstring',
      3 => 'openssl',
      4 => 'pdo',
      5 => 'tokenizer',
      6 => 'xml',
      7 => 'zip',
    ),
    'required_writable_paths' => 
    array (
      0 => 'C:\\xampp\\htdocs\\my-custom-cms\\storage',
      1 => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\framework',
      2 => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\logs',
      3 => 'C:\\xampp\\htdocs\\my-custom-cms\\bootstrap/cache',
    ),
    'required_env' => 
    array (
      0 => 'APP_KEY',
      1 => 'APP_URL',
      2 => 'DB_CONNECTION',
      3 => 'CACHE_STORE',
      4 => 'SESSION_DRIVER',
      5 => 'QUEUE_CONNECTION',
      6 => 'MAIL_MAILER',
    ),
    'production_forbidden' => 
    array (
      'APP_DEBUG' => 
      array (
        0 => 'true',
        1 => '1',
      ),
      'APP_ENV' => 
      array (
        0 => 'local',
        1 => 'testing',
      ),
      'QUEUE_CONNECTION' => 
      array (
        0 => 'sync',
      ),
      'MAIL_MAILER' => 
      array (
        0 => 'log',
        1 => 'array',
      ),
    ),
  ),
  'cosmic-legal' => 
  array (
    'terms_version' => '2026-08-05',
    'privacy_version' => '2026-08-05',
    'cookie_version' => '2026-08-05',
    'company_name' => 'Cosmic CMS',
    'contact_email' => '',
    'effective_date' => 'August 5, 2026',
  ),
  'cosmic-mail' => 
  array (
    'trial_mail_enabled' => true,
    'public_url' => 'http://127.0.0.1:8000',
    'dev_recipient' => 'genesisscagula@gmail.com',
  ),
  'cosmic-monitoring' => 
  array (
    'enabled' => true,
    'correlation_header' => 'X-Cosmic-Request-ID',
    'slow_query_ms' => 750,
    'health' => 
    array (
      'max_log_age_minutes' => 1440,
      'max_log_size_mb' => 100,
      'minimum_free_disk_mb' => 1024,
      'fail_on_unwritable_log_path' => true,
    ),
    'retention' => 
    array (
      'application_days' => 30,
      'security_days' => 90,
      'performance_days' => 14,
    ),
    'redacted_keys' => 
    array (
      0 => 'password',
      1 => 'password_confirmation',
      2 => 'current_password',
      3 => 'token',
      4 => 'access_token',
      5 => 'refresh_token',
      6 => 'authorization',
      7 => 'cookie',
      8 => 'client_secret',
      9 => 'secret',
      10 => 'api_key',
      11 => 'paypal_client_secret',
      12 => 'deployment_connector_secret',
    ),
  ),
  'cosmic-onboarding-qa' => 
  array (
    'stale_pending_hours' => 24,
    'stale_processing_minutes' => 30,
    'schedule_daily_at' => '09:35',
  ),
  'cosmic-plans' => 
  array (
    'starter' => 
    array (
      'label' => 'Starter',
      'family' => 'personal',
      'tier' => 'starter',
      'rank' => 10,
      'price_usd' => 49,
      'credits' => 500,
      'description' => 'Launch one complete business website with essential AI and Sparks.',
      'billing' => 
      array (
        'paypal_plan_id' => 'P-89030877PH1417334NJYFQLY',
      ),
      'capabilities' => 
      array (
        'max_sites' => 1,
        'max_pages_per_site' => 5,
        'max_sparks_per_site' => 15,
        'max_owned_sparks' => 15,
        'free_built_in_sparks' => 5,
        'template_limit' => 5,
        'template_access_level' => 'starter',
        'spark_access_level' => 'free',
        'analytics_level' => 'basic',
        'leads_level' => 'inbox',
        'sales_level' => 'none',
        'team_members' => 0,
        'white_label_level' => 'none',
        'api_access' => false,
        'ai_website_generation' => true,
        'ai_content_generation' => true,
        'ai_image_selection' => true,
        'theme_customization' => true,
        'header_footer_builder' => true,
        'contact_forms' => true,
        'blog_level' => 'basic',
        'landing_pages' => false,
        'lead_management' => false,
        'form_submission_management' => false,
        'website_duplicate_draft' => false,
        'custom_scripts' => false,
        'custom_forms' => false,
        'booking_ui_sparks' => false,
        'version_history' => false,
        'redirect_management' => false,
        'priority_ai' => false,
        'branding_removed' => false,
        'custom_domain' => false,
        'export_static' => true,
        'seo_level' => 'basic',
        'support_level' => 'standard',
      ),
    ),
    'growth' => 
    array (
      'label' => 'Growth',
      'family' => 'personal',
      'tier' => 'growth',
      'rank' => 20,
      'price_usd' => 79,
      'credits' => 1000,
      'description' => 'More pages, premium creative access, and marketing tools for a growing business.',
      'billing' => 
      array (
        'paypal_plan_id' => 'P-2BJ36023PG795122WNJYFUMA',
      ),
      'capabilities' => 
      array (
        'max_sites' => 1,
        'max_pages_per_site' => 10,
        'max_sparks_per_site' => 30,
        'max_owned_sparks' => 30,
        'free_built_in_sparks' => 10,
        'template_limit' => 10,
        'template_access_level' => 'growth',
        'spark_access_level' => 'growth',
        'analytics_level' => 'standard',
        'leads_level' => 'history',
        'sales_level' => 'basic',
        'team_members' => 0,
        'white_label_level' => 'none',
        'api_access' => false,
        'ai_website_generation' => true,
        'ai_content_generation' => true,
        'ai_image_selection' => true,
        'theme_customization' => true,
        'header_footer_builder' => true,
        'contact_forms' => true,
        'blog_level' => 'enhanced',
        'landing_pages' => true,
        'lead_management' => true,
        'form_submission_management' => true,
        'website_duplicate_draft' => true,
        'custom_scripts' => true,
        'custom_forms' => false,
        'booking_ui_sparks' => false,
        'version_history' => false,
        'redirect_management' => false,
        'priority_ai' => false,
        'branding_removed' => false,
        'custom_domain' => false,
        'export_static' => true,
        'seo_level' => 'enhanced',
        'support_level' => 'priority_email',
      ),
    ),
    'pro' => 
    array (
      'label' => 'Pro',
      'family' => 'personal',
      'tier' => 'pro',
      'rank' => 30,
      'price_usd' => 129,
      'credits' => 2000,
      'description' => 'The complete single-website Cosmic experience with advanced AI, analytics, leads, and sales.',
      'billing' => 
      array (
        'paypal_plan_id' => 'P-5NV88027NF4778352NJYFV5Y',
      ),
      'capabilities' => 
      array (
        'max_sites' => 1,
        'max_pages_per_site' => NULL,
        'max_sparks_per_site' => NULL,
        'max_owned_sparks' => NULL,
        'free_built_in_sparks' => 15,
        'template_limit' => NULL,
        'template_access_level' => 'pro',
        'spark_access_level' => 'pro',
        'analytics_level' => 'advanced',
        'leads_level' => 'full',
        'sales_level' => 'full',
        'team_members' => 0,
        'white_label_level' => 'branding_removed',
        'api_access' => false,
        'ai_website_generation' => true,
        'ai_content_generation' => true,
        'ai_image_selection' => true,
        'theme_customization' => true,
        'header_footer_builder' => true,
        'contact_forms' => true,
        'blog_level' => 'advanced',
        'landing_pages' => true,
        'lead_management' => true,
        'form_submission_management' => true,
        'website_duplicate_draft' => true,
        'custom_scripts' => true,
        'custom_forms' => true,
        'booking_ui_sparks' => true,
        'version_history' => true,
        'redirect_management' => true,
        'priority_ai' => true,
        'branding_removed' => true,
        'custom_domain' => true,
        'export_static' => true,
        'seo_level' => 'advanced',
        'support_level' => 'priority',
      ),
    ),
    'agency_starter' => 
    array (
      'label' => 'Starter Agency',
      'family' => 'agency',
      'tier' => 'starter',
      'rank' => 110,
      'price_usd' => 99,
      'credits' => 750,
      'description' => 'Manage up to 3 client websites with shared credits, previews, and essential agency tools.',
      'billing' => 
      array (
        'paypal_plan_id' => 'P-2MU22639SW558903MNJY5YTY',
      ),
      'capabilities' => 
      array (
        'max_sites' => 3,
        'max_pages_per_site' => 5,
        'max_sparks_per_site' => 15,
        'max_owned_sparks' => 15,
        'free_built_in_sparks' => 5,
        'template_limit' => 3,
        'marketplace_preview_limit' => 15,
        'template_access_level' => 'agency_starter',
        'spark_access_level' => 'agency_starter',
        'analytics_level' => 'per_website',
        'leads_level' => 'per_website',
        'sales_level' => 'none',
        'team_members' => 0,
        'white_label_level' => 'none',
        'api_access' => false,
        'website_clone' => true,
        'client_preview_links' => true,
        'website_search_filter' => true,
        'per_website_lead_inbox' => true,
        'per_website_analytics_summary' => true,
        'agency_workspace' => true,
        'activity_history' => true,
        'aggregated_analytics' => false,
        'aggregated_leads' => false,
        'aggregated_sales' => false,
        'client_handoff' => false,
        'shared_sparks' => false,
        'shared_templates' => false,
        'custom_preview_branding' => false,
        'bulk_export' => false,
      ),
    ),
    'agency_growth' => 
    array (
      'label' => 'Growth Agency',
      'family' => 'agency',
      'tier' => 'growth',
      'rank' => 120,
      'price_usd' => 199,
      'credits' => 1500,
      'description' => 'Operate up to 10 client websites with aggregated insights, shared assets, and team access.',
      'billing' => 
      array (
        'paypal_plan_id' => 'P-8EL43485LU352442XNJY5Y6Q',
      ),
      'capabilities' => 
      array (
        'max_sites' => 10,
        'max_pages_per_site' => 10,
        'max_sparks_per_site' => 30,
        'max_owned_sparks' => 30,
        'free_built_in_sparks' => 10,
        'template_limit' => 10,
        'marketplace_preview_limit' => 30,
        'template_access_level' => 'agency_growth',
        'spark_access_level' => 'agency_growth',
        'analytics_level' => 'aggregated',
        'leads_level' => 'aggregated',
        'sales_level' => 'summary',
        'team_members' => 3,
        'white_label_level' => 'basic',
        'api_access' => false,
        'website_clone' => true,
        'client_preview_links' => true,
        'shared_assets' => true,
        'website_search_filter' => true,
        'per_website_lead_inbox' => true,
        'per_website_analytics_summary' => true,
        'agency_workspace' => true,
        'activity_history' => true,
        'agency_insights' => true,
        'aggregated_analytics' => true,
        'aggregated_leads' => true,
        'aggregated_sales' => false,
        'website_date_filtering' => true,
        'client_handoff' => true,
        'ownership_transfer' => false,
        'shared_sparks' => true,
        'shared_templates' => true,
        'custom_preview_branding' => true,
        'branded_reports' => false,
        'advanced_white_label' => false,
        'granular_permissions' => false,
        'bulk_actions' => false,
        'bulk_export' => false,
        'client_accounts' => false,
        'revenue_reporting' => false,
        'conversion_reporting' => false,
        'lead_source_reporting' => false,
        'sales_funnel_reporting' => false,
        'ai_insights_foundation' => false,
        'webhooks' => false,
        'priority_ai' => false,
        'advanced_publication_history' => false,
        'early_access' => false,
      ),
    ),
    'agency_pro' => 
    array (
      'label' => 'Pro Agency',
      'family' => 'agency',
      'tier' => 'pro',
      'rank' => 130,
      'price_usd' => 399,
      'credits' => 3000,
      'description' => 'Unlimited websites with full agency insights, teams, sales, leads, white label, API, and webhooks.',
      'billing' => 
      array (
        'paypal_plan_id' => 'P-0VP21753X2440811ENJY5ZOQ',
      ),
      'capabilities' => 
      array (
        'max_sites' => NULL,
        'max_pages_per_site' => NULL,
        'max_sparks_per_site' => NULL,
        'max_owned_sparks' => NULL,
        'free_built_in_sparks' => 15,
        'template_limit' => NULL,
        'marketplace_preview_limit' => NULL,
        'template_access_level' => 'all',
        'spark_access_level' => 'all',
        'analytics_level' => 'full_agency',
        'leads_level' => 'full_agency',
        'sales_level' => 'full_agency',
        'team_members' => 10,
        'white_label_level' => 'full',
        'api_access' => true,
        'webhooks' => true,
        'website_clone' => true,
        'client_preview_links' => true,
        'shared_assets' => true,
        'bulk_actions' => true,
        'client_accounts' => true,
        'website_search_filter' => true,
        'per_website_lead_inbox' => true,
        'per_website_analytics_summary' => true,
        'agency_workspace' => true,
        'activity_history' => true,
        'agency_insights' => true,
        'aggregated_analytics' => true,
        'aggregated_leads' => true,
        'aggregated_sales' => true,
        'revenue_reporting' => true,
        'conversion_reporting' => true,
        'lead_source_reporting' => true,
        'sales_funnel_reporting' => true,
        'ai_insights_foundation' => true,
        'website_date_filtering' => true,
        'client_handoff' => true,
        'ownership_transfer' => true,
        'shared_sparks' => true,
        'shared_templates' => true,
        'custom_preview_branding' => true,
        'branded_reports' => true,
        'advanced_white_label' => true,
        'granular_permissions' => true,
        'priority_ai' => true,
        'advanced_publication_history' => true,
        'bulk_export' => true,
        'early_access' => true,
      ),
    ),
  ),
  'cosmic-queue' => 
  array (
    'queues' => 
    array (
      'mail' => 'mail',
      'maintenance' => 'maintenance',
    ),
    'health' => 
    array (
      'max_pending_jobs' => 500,
      'max_failed_jobs' => 25,
      'stale_after_minutes' => 15,
    ),
    'performance' => 
    array (
      'image_job_timeout' => 150,
      'image_job_retry_window_minutes' => 10,
      'image_connect_timeout' => 4,
      'image_download_timeout' => 10,
      'image_download_retries' => 1,
      'image_retry_backoff' => 
      array (
        0 => 2,
        1 => 5,
        2 => 10,
        3 => 20,
      ),
      'builder_poll_interval_ms' => 2500,
    ),
    'retention' => 
    array (
      'failed_job_hours' => 336,
      'expired_access_days' => 30,
    ),
  ),
  'cosmic-rate-limits' => 
  array (
    'auth' => 
    array (
      'login_per_minute' => 5,
      'register_per_hour' => 8,
      'password_reset_per_hour' => 5,
    ),
    'trial' => 
    array (
      'create_per_hour' => 10,
      'email_per_hour' => 8,
      'regenerate_per_week' => 2,
    ),
    'ai' => 
    array (
      'per_minute' => 12,
      'per_hour' => 120,
    ),
    'uploads' => 
    array (
      'per_minute' => 20,
      'per_hour' => 200,
    ),
    'public_preview' => 
    array (
      'per_minute' => 120,
    ),
    'forms' => 
    array (
      'per_minute' => 10,
      'per_hour' => 60,
    ),
    'workspace_writes' => 
    array (
      'per_minute' => 30,
    ),
    'api' => 
    array (
      'analytics_per_minute' => 240,
      'contact_per_minute' => 30,
      'bridge_per_minute' => 120,
      'webhook_per_minute' => 120,
    ),
  ),
  'cosmic-seo' => 
  array (
    'site_name' => 'Cosmic CMS',
    'base_url' => 'http://127.0.0.1:8000',
    'default_title' => 'AI Website Builder for Modern Business Websites | Cosmic CMS',
    'default_description' => 'Build a modern, responsive business website with Cosmic CMS, an AI website builder for generating, customizing, and publishing professional websites faster.',
    'default_image' => '/images/cosmic-cms-social-preview.png',
    'twitter_card' => 'summary_large_image',
  ),
  'cosmic-sparks' => 
  array (
    'access_levels' => 
    array (
      'free' => 
      array (
        'rank' => 0,
        'label' => 'Free',
      ),
      'starter' => 
      array (
        'rank' => 10,
        'label' => 'Starter',
      ),
      'growth' => 
      array (
        'rank' => 20,
        'label' => 'Growth',
      ),
      'pro' => 
      array (
        'rank' => 30,
        'label' => 'Pro',
      ),
      'agency_starter' => 
      array (
        'rank' => 110,
        'label' => 'Starter Agency',
      ),
      'agency_growth' => 
      array (
        'rank' => 120,
        'label' => 'Growth Agency',
      ),
      'agency_pro' => 
      array (
        'rank' => 130,
        'label' => 'Pro Agency',
      ),
      'all' => 
      array (
        'rank' => 9223372036854775807,
        'label' => 'All Sparks',
      ),
    ),
    'collections' => 
    array (
      'core' => 
      array (
        'label' => 'Core Collection',
        'access_level' => 'free',
        'description' => 'Essential reusable sections available to every paid Cosmic plan.',
      ),
      'growth' => 
      array (
        'label' => 'Growth Collection',
        'access_level' => 'growth',
        'description' => 'Marketing-focused layouts for Growth and higher plans.',
      ),
      'signature' => 
      array (
        'label' => 'Signature Collection',
        'access_level' => 'pro',
        'description' => 'Premium visual and motion sections for Pro and higher plans.',
      ),
    ),
    'overrides' => 
    array (
    ),
  ),
  'cosmic-templates' => 
  array (
    'access_levels' => 
    array (
      'starter' => 10,
      'growth' => 20,
      'pro' => 30,
      'agency_starter' => 110,
      'agency_growth' => 120,
      'agency_pro' => 130,
      'all' => 9223372036854775807,
    ),
    'agency_collections' => 
    array (
      'agency_starter' => 
      array (
        'label' => 'Agency Essentials',
        'description' => 'Three versatile client-ready foundations for solo freelancers and small agencies.',
        'minimum_plan' => 'agency_starter',
        'templates' => 
        array (
          0 => 'aurora-agency',
          1 => 'summit-consulting',
          2 => 'nova-startup',
        ),
      ),
      'agency_growth' => 
      array (
        'label' => 'Agency Growth Collection',
        'description' => 'Ten curated templates covering business, hospitality, construction, and startup clients.',
        'minimum_plan' => 'agency_growth',
        'templates' => 
        array (
          0 => 'aurora-agency',
          1 => 'summit-consulting',
          2 => 'nova-startup',
          3 => 'midnight-studio',
          4 => 'table-tide',
          5 => 'ember-kitchen',
          6 => 'olive-hearth',
          7 => 'morning-brew',
          8 => 'roast-lab',
          9 => 'buildcore',
        ),
      ),
      'agency_pro' => 
      array (
        'label' => 'Agency Pro Library',
        'description' => 'The complete Cosmic template catalog for agencies serving any supported industry.',
        'minimum_plan' => 'agency_pro',
        'templates' => 
        array (
          0 => '*',
        ),
      ),
    ),
    'templates' => 
    array (
      'aurora-agency' => 
      array (
        'minimum_plan' => 'starter',
        'industry' => 'Creative agency',
        'theme_family' => 'violet',
        'page_count' => 1,
        'spark_collection' => 'aurora-agency-starter',
        'is_featured' => true,
        'is_premium' => false,
        'collection' => 'personal',
      ),
      'summit-consulting' => 
      array (
        'minimum_plan' => 'starter',
        'industry' => 'Business consulting',
        'theme_family' => 'navy',
        'page_count' => 1,
        'spark_collection' => 'summit-consulting-starter',
        'is_featured' => false,
        'is_premium' => false,
        'collection' => 'personal',
      ),
      'nova-startup' => 
      array (
        'minimum_plan' => 'starter',
        'industry' => 'Startup',
        'theme_family' => 'indigo',
        'page_count' => 1,
        'spark_collection' => 'nova-startup-starter',
        'is_featured' => false,
        'is_premium' => false,
        'collection' => 'personal',
      ),
      'midnight-studio' => 
      array (
        'minimum_plan' => 'starter',
        'industry' => 'Professional studio',
        'theme_family' => 'midnight',
        'page_count' => 1,
        'spark_collection' => 'midnight-studio-starter',
        'is_featured' => false,
        'is_premium' => false,
        'collection' => 'personal',
      ),
      'table-tide' => 
      array (
        'minimum_plan' => 'starter',
        'industry' => 'Restaurant',
        'theme_family' => 'terracotta',
        'page_count' => 1,
        'spark_collection' => 'table-tide-starter',
        'is_featured' => true,
        'is_premium' => false,
        'collection' => 'personal',
      ),
      'ember-kitchen' => 
      array (
        'minimum_plan' => 'growth',
        'industry' => 'Modern dining',
        'theme_family' => 'espresso',
        'page_count' => 1,
        'spark_collection' => 'ember-kitchen-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'olive-hearth' => 
      array (
        'minimum_plan' => 'growth',
        'industry' => 'Mediterranean restaurant',
        'theme_family' => 'olive',
        'page_count' => 1,
        'spark_collection' => 'olive-hearth-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'morning-brew' => 
      array (
        'minimum_plan' => 'growth',
        'industry' => 'Coffee shop',
        'theme_family' => 'coffee',
        'page_count' => 1,
        'spark_collection' => 'morning-brew-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'roast-lab' => 
      array (
        'minimum_plan' => 'growth',
        'industry' => 'Coffee roaster',
        'theme_family' => 'espresso',
        'page_count' => 1,
        'spark_collection' => 'roast-lab-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'buildcore' => 
      array (
        'minimum_plan' => 'growth',
        'industry' => 'Construction',
        'theme_family' => 'asphalt',
        'page_count' => 1,
        'spark_collection' => 'buildcore-starter',
        'is_featured' => true,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'skyline-builders' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Residential builder',
        'theme_family' => 'stone',
        'page_count' => 1,
        'spark_collection' => 'skyline-builders-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'forge-works' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Industrial services',
        'theme_family' => 'charcoal',
        'page_count' => 1,
        'spark_collection' => 'forge-works-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'carepoint' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Medical clinic',
        'theme_family' => 'ocean',
        'page_count' => 1,
        'spark_collection' => 'carepoint-starter',
        'is_featured' => true,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'mednova' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Specialist practice',
        'theme_family' => 'teal',
        'page_count' => 1,
        'spark_collection' => 'mednova-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'smile-studio' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Dental clinic',
        'theme_family' => 'sapphire',
        'page_count' => 1,
        'spark_collection' => 'smile-studio-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'iron-gym' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Gym',
        'theme_family' => 'ruby',
        'page_count' => 1,
        'spark_collection' => 'iron-gym-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'motion-studio' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Boutique fitness',
        'theme_family' => 'plum',
        'page_count' => 1,
        'spark_collection' => 'motion-studio-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'haven-estates' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Real estate agency',
        'theme_family' => 'emerald',
        'page_count' => 1,
        'spark_collection' => 'haven-estates-starter',
        'is_featured' => true,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'prime-homes' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Property sales',
        'theme_family' => 'forest',
        'page_count' => 1,
        'spark_collection' => 'prime-homes-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'learnhub' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Online education',
        'theme_family' => 'indigo',
        'page_count' => 1,
        'spark_collection' => 'learnhub-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'bright-academy' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'School or academy',
        'theme_family' => 'amber',
        'page_count' => 1,
        'spark_collection' => 'bright-academy-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'cloudtech' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Technology services',
        'theme_family' => 'sapphire',
        'page_count' => 1,
        'spark_collection' => 'cloudtech-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'orbit-launch' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'SaaS',
        'theme_family' => 'violet',
        'page_count' => 1,
        'spark_collection' => 'orbit-launch-starter',
        'is_featured' => true,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'horizon-travel' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Travel agency',
        'theme_family' => 'teal',
        'page_count' => 1,
        'spark_collection' => 'horizon-travel-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'atlas-escape' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Luxury travel',
        'theme_family' => 'navy',
        'page_count' => 1,
        'spark_collection' => 'atlas-escape-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'legacy-law' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Law firm',
        'theme_family' => 'navy',
        'page_count' => 1,
        'spark_collection' => 'legacy-law-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'justice-partners' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Legal practice',
        'theme_family' => 'charcoal',
        'page_count' => 1,
        'spark_collection' => 'justice-partners-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'obsidian-atelier' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Luxury brand',
        'theme_family' => 'obsidian',
        'page_count' => 1,
        'spark_collection' => 'obsidian-atelier-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
      'form-function' => 
      array (
        'minimum_plan' => 'pro',
        'industry' => 'Design portfolio',
        'theme_family' => 'slate',
        'page_count' => 1,
        'spark_collection' => 'form-function-starter',
        'is_featured' => false,
        'is_premium' => true,
        'collection' => 'personal',
      ),
    ),
  ),
  'cosmic-tracking' => 
  array (
    'google_site_verification' => NULL,
    'google_analytics_id' => NULL,
    'google_tag_manager_id' => 'GTM-TX6FZL26',
    'google_ads_id' => NULL,
  ),
  'cosmic_media' => 
  array (
    'localize_remote_images' => false,
  ),
  'database' => 
  array (
    'default' => 'sqlite',
    'connections' => 
    array (
      'sqlite' => 
      array (
        'driver' => 'sqlite',
        'url' => NULL,
        'database' => 'C:\\xampp\\htdocs\\my-custom-cms\\database\\database.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => NULL,
        'journal_mode' => NULL,
        'synchronous' => NULL,
        'transaction_mode' => 'DEFERRED',
      ),
      'mysql' => 
      array (
        'driver' => 'mysql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'laravel',
        'username' => 'root',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'mariadb' => 
      array (
        'driver' => 'mariadb',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'laravel',
        'username' => 'root',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'pgsql' => 
      array (
        'driver' => 'pgsql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '5432',
        'database' => 'laravel',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
      ),
      'sqlsrv' => 
      array (
        'driver' => 'sqlsrv',
        'url' => NULL,
        'host' => 'localhost',
        'port' => '1433',
        'database' => 'laravel',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
      ),
    ),
    'migrations' => 
    array (
      'table' => 'migrations',
      'update_date_on_publish' => true,
    ),
    'redis' => 
    array (
      'client' => 'phpredis',
      'options' => 
      array (
        'cluster' => 'redis',
        'prefix' => 'laravel-database-',
        'persistent' => false,
      ),
      'default' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '0',
        'max_retries' => 3,
        'backoff_algorithm' => 'decorrelated_jitter',
        'backoff_base' => 100,
        'backoff_cap' => 1000,
      ),
      'cache' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '1',
        'max_retries' => 3,
        'backoff_algorithm' => 'decorrelated_jitter',
        'backoff_base' => 100,
        'backoff_cap' => 1000,
      ),
    ),
  ),
  'filesystems' => 
  array (
    'default' => 'local',
    'disks' => 
    array (
      'local' => 
      array (
        'driver' => 'local',
        'root' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\app/private',
        'serve' => true,
        'throw' => false,
        'report' => false,
      ),
      'public' => 
      array (
        'driver' => 'local',
        'root' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\app/public',
        'url' => 'http://127.0.0.1:8000/storage',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
      ),
      's3' => 
      array (
        'driver' => 's3',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'bucket' => '',
        'url' => NULL,
        'endpoint' => NULL,
        'use_path_style_endpoint' => false,
        'throw' => false,
        'report' => false,
      ),
    ),
    'links' => 
    array (
      'C:\\xampp\\htdocs\\my-custom-cms\\public\\storage' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\app/public',
    ),
  ),
  'logging' => 
  array (
    'default' => 'stack',
    'deprecations' => 
    array (
      'channel' => NULL,
      'trace' => false,
    ),
    'channels' => 
    array (
      'stack' => 
      array (
        'driver' => 'stack',
        'channels' => 
        array (
          0 => 'single',
        ),
        'ignore_exceptions' => false,
      ),
      'single' => 
      array (
        'driver' => 'single',
        'path' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\logs/laravel.log',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'daily' => 
      array (
        'driver' => 'daily',
        'path' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\logs/laravel.log',
        'level' => 'debug',
        'days' => 14,
        'replace_placeholders' => true,
      ),
      'slack' => 
      array (
        'driver' => 'slack',
        'url' => NULL,
        'username' => 'Laravel',
        'emoji' => ':boom:',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'papertrail' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\SyslogUdpHandler',
        'handler_with' => 
        array (
          'host' => NULL,
          'port' => NULL,
          'connectionString' => 'tls://:',
        ),
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'stderr' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\StreamHandler',
        'handler_with' => 
        array (
          'stream' => 'php://stderr',
        ),
        'formatter' => NULL,
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'syslog' => 
      array (
        'driver' => 'syslog',
        'level' => 'debug',
        'facility' => 8,
        'replace_placeholders' => true,
      ),
      'errorlog' => 
      array (
        'driver' => 'errorlog',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'null' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\NullHandler',
      ),
      'emergency' => 
      array (
        'path' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\logs/laravel.log',
      ),
      'cosmic_errors' => 
      array (
        'driver' => 'daily',
        'path' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\logs/cosmic-errors.log',
        'level' => 'error',
        'days' => '30',
        'replace_placeholders' => true,
      ),
      'cosmic_security' => 
      array (
        'driver' => 'daily',
        'path' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\logs/cosmic-security.log',
        'level' => 'notice',
        'days' => '90',
        'replace_placeholders' => true,
      ),
      'cosmic_performance' => 
      array (
        'driver' => 'daily',
        'path' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\logs/cosmic-performance.log',
        'level' => 'warning',
        'days' => '14',
        'replace_placeholders' => true,
      ),
    ),
  ),
  'mail' => 
  array (
    'default' => 'smtp',
    'mailers' => 
    array (
      'smtp' => 
      array (
        'transport' => 'smtp',
        'scheme' => NULL,
        'url' => NULL,
        'host' => 'smtp.resend.com',
        'port' => '587',
        'username' => 'resend',
        'password' => 're_VbHsi7s3_ChpjiFaAbzFeucbgz9brfU9b',
        'timeout' => NULL,
        'local_domain' => '127.0.0.1',
      ),
      'ses' => 
      array (
        'transport' => 'ses',
      ),
      'postmark' => 
      array (
        'transport' => 'postmark',
      ),
      'resend' => 
      array (
        'transport' => 'resend',
      ),
      'sendmail' => 
      array (
        'transport' => 'sendmail',
        'path' => '/usr/sbin/sendmail -bs -i',
      ),
      'log' => 
      array (
        'transport' => 'log',
        'channel' => NULL,
      ),
      'array' => 
      array (
        'transport' => 'array',
      ),
      'failover' => 
      array (
        'transport' => 'failover',
        'mailers' => 
        array (
          0 => 'smtp',
          1 => 'log',
        ),
        'retry_after' => 60,
      ),
      'roundrobin' => 
      array (
        'transport' => 'roundrobin',
        'mailers' => 
        array (
          0 => 'ses',
          1 => 'postmark',
        ),
        'retry_after' => 60,
      ),
    ),
    'from' => 
    array (
      'address' => 'hello@cosmiccms.com',
      'name' => 'Cosmic CMS',
    ),
    'markdown' => 
    array (
      'theme' => 'default',
      'paths' => 
      array (
        0 => 'C:\\xampp\\htdocs\\my-custom-cms\\resources\\views/vendor/mail',
      ),
      'extensions' => 
      array (
      ),
    ),
  ),
  'openai' => 
  array (
    'api_key' => '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',
    'organization' => NULL,
    'project' => NULL,
    'base_uri' => NULL,
    'request_timeout' => 180,
    'planner_model' => 'gpt-5.6-luna',
    'visual_model' => 'gpt-5.6-luna',
    'pipeline_stage_attempts' => 2,
    'pipeline_retry_delay_ms' => 150,
    'media_pack_queue' => 'images-high',
    'media_pack_initial_target' => 6,
    'trial_remote_images_enabled' => true,
    'remote_preview_images_enabled' => true,
    'registered_remote_images_enabled' => true,
    'parallel_engine_enabled' => true,
    'content_model' => 'gpt-5.6-luna',
    'content_json_attempts' => 2,
    'visual_cache_enabled' => true,
    'visual_cache_ttl' => 86400,
    'schema_cache_enabled' => true,
    'schema_cache_ttl' => 86400,
    'ai_cache_jitter_percent' => 10,
    'planner_cache_enabled' => false,
    'planner_cache_ttl' => 86400,
    'content_cache_enabled' => false,
    'content_cache_ttl' => 3600,
  ),
  'payments' => 
  array (
    'routing' => 
    array (
      'philippines_country_code' => 'PH',
      'philippines_provider' => 'paymongo',
      'international_provider' => 'paypal',
      'fallback_provider' => 'paypal',
      'country_header' => 'CF-IPCountry',
    ),
    'paypal' => 
    array (
      'enabled' => true,
      'mode' => 'sandbox',
      'client_id' => 'BAAk47ZhDvz3nszWyBVkkF407E6njLcU0zJ94Zefq1mW3qyjmbDfMl_d6Lo-6E32ESB_evB_SwYEiW4TM0',
      'client_secret' => 'ENh9OVCgh0ZY2ZjkH03wtEq5mJwHgX2RQYDpYeqw-pCcn8hVeI2Vqvlvi27Z658iqK41OsTLc6-F_na2',
      'webhook_id' => '94P061817M448723E',
      'currency' => 'USD',
      'plan_ids' => 
      array (
        'starter' => 'P-89030877PH1417334NJYFQLY',
        'growth' => 'P-2BJ36023PG795122WNJYFUMA',
        'pro' => 'P-5NV88027NF4778352NJYFV5Y',
        'agency_starter' => 'P-2MU22639SW558903MNJY5YTY',
        'agency_growth' => 'P-8EL43485LU352442XNJY5Y6Q',
        'agency_pro' => 'P-0VP21753X2440811ENJY5ZOQ',
      ),
      'base_url' => 'https://api-m.sandbox.paypal.com',
    ),
    'paymongo' => 
    array (
      'enabled' => true,
      'secret' => '',
      'webhook_secret' => '',
      'methods' => 
      array (
        0 => 'card',
        1 => 'gcash',
        2 => 'paymaya',
      ),
      'currency' => 'PHP',
    ),
    'stripe' => 
    array (
      'enabled' => false,
      'secret' => NULL,
      'webhook_secret' => NULL,
    ),
    'website_upgrade_options' => 
    array (
      0 => 
      array (
        'key' => 'agency_starter',
        'label' => 'Starter Agency',
        'sites' => 'Up to 3 websites',
        'description' => 'For freelancers managing a small client portfolio.',
      ),
      1 => 
      array (
        'key' => 'agency_growth',
        'label' => 'Growth Agency',
        'sites' => 'Up to 10 websites',
        'description' => 'For growing teams managing multiple active clients.',
      ),
      2 => 
      array (
        'key' => 'agency_pro',
        'label' => 'Pro Agency',
        'sites' => 'Unlimited websites',
        'description' => 'Full agency operations, insights, teams, and white label.',
      ),
    ),
    'plans' => 
    array (
      'starter' => 
      array (
        'label' => 'Starter',
        'family' => 'personal',
        'tier' => 'starter',
        'rank' => 10,
        'price_usd' => 49,
        'credits' => 500,
        'description' => 'Launch one complete business website with essential AI and Sparks.',
        'billing' => 
        array (
          'paypal_plan_id' => 'P-89030877PH1417334NJYFQLY',
        ),
        'capabilities' => 
        array (
          'max_sites' => 1,
          'max_pages_per_site' => 5,
          'max_sparks_per_site' => 15,
          'max_owned_sparks' => 15,
          'free_built_in_sparks' => 5,
          'template_limit' => 5,
          'template_access_level' => 'starter',
          'spark_access_level' => 'free',
          'analytics_level' => 'basic',
          'leads_level' => 'inbox',
          'sales_level' => 'none',
          'team_members' => 0,
          'white_label_level' => 'none',
          'api_access' => false,
          'ai_website_generation' => true,
          'ai_content_generation' => true,
          'ai_image_selection' => true,
          'theme_customization' => true,
          'header_footer_builder' => true,
          'contact_forms' => true,
          'blog_level' => 'basic',
          'landing_pages' => false,
          'lead_management' => false,
          'form_submission_management' => false,
          'website_duplicate_draft' => false,
          'custom_scripts' => false,
          'custom_forms' => false,
          'booking_ui_sparks' => false,
          'version_history' => false,
          'redirect_management' => false,
          'priority_ai' => false,
          'branding_removed' => false,
          'custom_domain' => false,
          'export_static' => true,
          'seo_level' => 'basic',
          'support_level' => 'standard',
        ),
      ),
      'growth' => 
      array (
        'label' => 'Growth',
        'family' => 'personal',
        'tier' => 'growth',
        'rank' => 20,
        'price_usd' => 79,
        'credits' => 1000,
        'description' => 'More pages, premium creative access, and marketing tools for a growing business.',
        'billing' => 
        array (
          'paypal_plan_id' => 'P-2BJ36023PG795122WNJYFUMA',
        ),
        'capabilities' => 
        array (
          'max_sites' => 1,
          'max_pages_per_site' => 10,
          'max_sparks_per_site' => 30,
          'max_owned_sparks' => 30,
          'free_built_in_sparks' => 10,
          'template_limit' => 10,
          'template_access_level' => 'growth',
          'spark_access_level' => 'growth',
          'analytics_level' => 'standard',
          'leads_level' => 'history',
          'sales_level' => 'basic',
          'team_members' => 0,
          'white_label_level' => 'none',
          'api_access' => false,
          'ai_website_generation' => true,
          'ai_content_generation' => true,
          'ai_image_selection' => true,
          'theme_customization' => true,
          'header_footer_builder' => true,
          'contact_forms' => true,
          'blog_level' => 'enhanced',
          'landing_pages' => true,
          'lead_management' => true,
          'form_submission_management' => true,
          'website_duplicate_draft' => true,
          'custom_scripts' => true,
          'custom_forms' => false,
          'booking_ui_sparks' => false,
          'version_history' => false,
          'redirect_management' => false,
          'priority_ai' => false,
          'branding_removed' => false,
          'custom_domain' => false,
          'export_static' => true,
          'seo_level' => 'enhanced',
          'support_level' => 'priority_email',
        ),
      ),
      'pro' => 
      array (
        'label' => 'Pro',
        'family' => 'personal',
        'tier' => 'pro',
        'rank' => 30,
        'price_usd' => 129,
        'credits' => 2000,
        'description' => 'The complete single-website Cosmic experience with advanced AI, analytics, leads, and sales.',
        'billing' => 
        array (
          'paypal_plan_id' => 'P-5NV88027NF4778352NJYFV5Y',
        ),
        'capabilities' => 
        array (
          'max_sites' => 1,
          'max_pages_per_site' => NULL,
          'max_sparks_per_site' => NULL,
          'max_owned_sparks' => NULL,
          'free_built_in_sparks' => 15,
          'template_limit' => NULL,
          'template_access_level' => 'pro',
          'spark_access_level' => 'pro',
          'analytics_level' => 'advanced',
          'leads_level' => 'full',
          'sales_level' => 'full',
          'team_members' => 0,
          'white_label_level' => 'branding_removed',
          'api_access' => false,
          'ai_website_generation' => true,
          'ai_content_generation' => true,
          'ai_image_selection' => true,
          'theme_customization' => true,
          'header_footer_builder' => true,
          'contact_forms' => true,
          'blog_level' => 'advanced',
          'landing_pages' => true,
          'lead_management' => true,
          'form_submission_management' => true,
          'website_duplicate_draft' => true,
          'custom_scripts' => true,
          'custom_forms' => true,
          'booking_ui_sparks' => true,
          'version_history' => true,
          'redirect_management' => true,
          'priority_ai' => true,
          'branding_removed' => true,
          'custom_domain' => true,
          'export_static' => true,
          'seo_level' => 'advanced',
          'support_level' => 'priority',
        ),
      ),
      'agency_starter' => 
      array (
        'label' => 'Starter Agency',
        'family' => 'agency',
        'tier' => 'starter',
        'rank' => 110,
        'price_usd' => 99,
        'credits' => 750,
        'description' => 'Manage up to 3 client websites with shared credits, previews, and essential agency tools.',
        'billing' => 
        array (
          'paypal_plan_id' => 'P-2MU22639SW558903MNJY5YTY',
        ),
        'capabilities' => 
        array (
          'max_sites' => 3,
          'max_pages_per_site' => 5,
          'max_sparks_per_site' => 15,
          'max_owned_sparks' => 15,
          'free_built_in_sparks' => 5,
          'template_limit' => 3,
          'marketplace_preview_limit' => 15,
          'template_access_level' => 'agency_starter',
          'spark_access_level' => 'agency_starter',
          'analytics_level' => 'per_website',
          'leads_level' => 'per_website',
          'sales_level' => 'none',
          'team_members' => 0,
          'white_label_level' => 'none',
          'api_access' => false,
          'website_clone' => true,
          'client_preview_links' => true,
          'website_search_filter' => true,
          'per_website_lead_inbox' => true,
          'per_website_analytics_summary' => true,
          'agency_workspace' => true,
          'activity_history' => true,
          'aggregated_analytics' => false,
          'aggregated_leads' => false,
          'aggregated_sales' => false,
          'client_handoff' => false,
          'shared_sparks' => false,
          'shared_templates' => false,
          'custom_preview_branding' => false,
          'bulk_export' => false,
        ),
      ),
      'agency_growth' => 
      array (
        'label' => 'Growth Agency',
        'family' => 'agency',
        'tier' => 'growth',
        'rank' => 120,
        'price_usd' => 199,
        'credits' => 1500,
        'description' => 'Operate up to 10 client websites with aggregated insights, shared assets, and team access.',
        'billing' => 
        array (
          'paypal_plan_id' => 'P-8EL43485LU352442XNJY5Y6Q',
        ),
        'capabilities' => 
        array (
          'max_sites' => 10,
          'max_pages_per_site' => 10,
          'max_sparks_per_site' => 30,
          'max_owned_sparks' => 30,
          'free_built_in_sparks' => 10,
          'template_limit' => 10,
          'marketplace_preview_limit' => 30,
          'template_access_level' => 'agency_growth',
          'spark_access_level' => 'agency_growth',
          'analytics_level' => 'aggregated',
          'leads_level' => 'aggregated',
          'sales_level' => 'summary',
          'team_members' => 3,
          'white_label_level' => 'basic',
          'api_access' => false,
          'website_clone' => true,
          'client_preview_links' => true,
          'shared_assets' => true,
          'website_search_filter' => true,
          'per_website_lead_inbox' => true,
          'per_website_analytics_summary' => true,
          'agency_workspace' => true,
          'activity_history' => true,
          'agency_insights' => true,
          'aggregated_analytics' => true,
          'aggregated_leads' => true,
          'aggregated_sales' => false,
          'website_date_filtering' => true,
          'client_handoff' => true,
          'ownership_transfer' => false,
          'shared_sparks' => true,
          'shared_templates' => true,
          'custom_preview_branding' => true,
          'branded_reports' => false,
          'advanced_white_label' => false,
          'granular_permissions' => false,
          'bulk_actions' => false,
          'bulk_export' => false,
          'client_accounts' => false,
          'revenue_reporting' => false,
          'conversion_reporting' => false,
          'lead_source_reporting' => false,
          'sales_funnel_reporting' => false,
          'ai_insights_foundation' => false,
          'webhooks' => false,
          'priority_ai' => false,
          'advanced_publication_history' => false,
          'early_access' => false,
        ),
      ),
      'agency_pro' => 
      array (
        'label' => 'Pro Agency',
        'family' => 'agency',
        'tier' => 'pro',
        'rank' => 130,
        'price_usd' => 399,
        'credits' => 3000,
        'description' => 'Unlimited websites with full agency insights, teams, sales, leads, white label, API, and webhooks.',
        'billing' => 
        array (
          'paypal_plan_id' => 'P-0VP21753X2440811ENJY5ZOQ',
        ),
        'capabilities' => 
        array (
          'max_sites' => NULL,
          'max_pages_per_site' => NULL,
          'max_sparks_per_site' => NULL,
          'max_owned_sparks' => NULL,
          'free_built_in_sparks' => 15,
          'template_limit' => NULL,
          'marketplace_preview_limit' => NULL,
          'template_access_level' => 'all',
          'spark_access_level' => 'all',
          'analytics_level' => 'full_agency',
          'leads_level' => 'full_agency',
          'sales_level' => 'full_agency',
          'team_members' => 10,
          'white_label_level' => 'full',
          'api_access' => true,
          'webhooks' => true,
          'website_clone' => true,
          'client_preview_links' => true,
          'shared_assets' => true,
          'bulk_actions' => true,
          'client_accounts' => true,
          'website_search_filter' => true,
          'per_website_lead_inbox' => true,
          'per_website_analytics_summary' => true,
          'agency_workspace' => true,
          'activity_history' => true,
          'agency_insights' => true,
          'aggregated_analytics' => true,
          'aggregated_leads' => true,
          'aggregated_sales' => true,
          'revenue_reporting' => true,
          'conversion_reporting' => true,
          'lead_source_reporting' => true,
          'sales_funnel_reporting' => true,
          'ai_insights_foundation' => true,
          'website_date_filtering' => true,
          'client_handoff' => true,
          'ownership_transfer' => true,
          'shared_sparks' => true,
          'shared_templates' => true,
          'custom_preview_branding' => true,
          'branded_reports' => true,
          'advanced_white_label' => true,
          'granular_permissions' => true,
          'priority_ai' => true,
          'advanced_publication_history' => true,
          'bulk_export' => true,
          'early_access' => true,
        ),
      ),
    ),
  ),
  'queue' => 
  array (
    'default' => 'database',
    'connections' => 
    array (
      'sync' => 
      array (
        'driver' => 'sync',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 240,
        'after_commit' => false,
      ),
      'beanstalkd' => 
      array (
        'driver' => 'beanstalkd',
        'host' => 'localhost',
        'queue' => 'default',
        'retry_after' => 240,
        'block_for' => 0,
        'after_commit' => false,
      ),
      'sqs' => 
      array (
        'driver' => 'sqs',
        'key' => '',
        'secret' => '',
        'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
        'queue' => 'default',
        'suffix' => NULL,
        'region' => 'us-east-1',
        'after_commit' => false,
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 240,
        'block_for' => NULL,
        'after_commit' => false,
      ),
      'deferred' => 
      array (
        'driver' => 'deferred',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'connections' => 
        array (
          0 => 'database',
          1 => 'deferred',
        ),
      ),
      'background' => 
      array (
        'driver' => 'background',
      ),
    ),
    'batching' => 
    array (
      'database' => 'sqlite',
      'table' => 'job_batches',
    ),
    'failed' => 
    array (
      'driver' => 'database-uuids',
      'database' => 'sqlite',
      'table' => 'failed_jobs',
    ),
  ),
  'sanctum' => 
  array (
    'stateful' => 
    array (
      0 => 'localhost',
      1 => 'localhost:3000',
      2 => '127.0.0.1',
      3 => '127.0.0.1:8000',
      4 => '::1',
      5 => '127.0.0.1:8000',
    ),
    'guard' => 
    array (
      0 => 'web',
    ),
    'expiration' => NULL,
    'token_prefix' => '',
    'middleware' => 
    array (
      'authenticate_session' => 'Laravel\\Sanctum\\Http\\Middleware\\AuthenticateSession',
      'encrypt_cookies' => 'Illuminate\\Cookie\\Middleware\\EncryptCookies',
      'validate_csrf_token' => 'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
    ),
  ),
  'services' => 
  array (
    'postmark' => 
    array (
      'key' => NULL,
    ),
    'resend' => 
    array (
      'key' => NULL,
    ),
    'ses' => 
    array (
      'key' => '',
      'secret' => '',
      'region' => 'us-east-1',
    ),
    'slack' => 
    array (
      'notifications' => 
      array (
        'bot_user_oauth_token' => NULL,
        'channel' => NULL,
      ),
    ),
    'smart_images' => 
    array (
      'provider' => 'unsplash',
      'providers' => 
      array (
        0 => 'unsplash',
        1 => 'pexels',
        2 => 'pixabay',
      ),
      'timeout' => 8,
      'cache' => true,
      'cache_ttl' => 2592000,
      'min_score' => 2,
      'trial_remote_budget' => 0,
      'query_builder_version' => '4.2.0.4',
      'ranking_version' => '4.2.0.4',
    ),
    'unsplash' => 
    array (
      'access_key' => 'QHfXLDpbWYf_J0hwScmJLpBToo8rJMrXBnzygfsSyXw',
    ),
    'pexels' => 
    array (
      'api_key' => 'BRPQTkZtSPCP3ds6TMext3Kj3lAj4QvauWIb1mbvChNme6SPvtQaZf8W',
    ),
    'pixabay' => 
    array (
      'api_key' => 'YOUR_PIXABAY_KEY',
    ),
    'cosmic' => 
    array (
      'publish_webhook_url' => NULL,
      'static_sync_url' => 'http://127.0.0.1/test-cms/sync.php?action=receive_package',
      'static_sync_token' => 'cosmic-static-sync-8f4c3a1d7e9b2f6c5a0d4e8b1c3f7a9d',
      'asset_base_url' => 'http://127.0.0.1:8000',
    ),
  ),
  'session' => 
  array (
    'driver' => 'database',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\framework/sessions',
    'connection' => NULL,
    'table' => 'sessions',
    'store' => NULL,
    'lottery' => 
    array (
      0 => 2,
      1 => 100,
    ),
    'cookie' => 'laravel-session',
    'path' => '/',
    'domain' => NULL,
    'secure' => NULL,
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
  ),
  'trial-navigation' => 
  array (
    'construction' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Services',
      3 => 'Projects',
      4 => 'Contact',
    ),
    'restaurant' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Menu',
      3 => 'Gallery',
      4 => 'Reservations',
    ),
    'coffee' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Menu',
      3 => 'Gallery',
      4 => 'Contact',
    ),
    'bakery' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Menu',
      3 => 'Custom Cakes',
      4 => 'Contact',
    ),
    'dentist' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Treatments',
      3 => 'Team',
      4 => 'Book Appointment',
    ),
    'medical' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Services',
      3 => 'Doctors',
      4 => 'Appointments',
    ),
    'lawyer' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Practice Areas',
      3 => 'Case Results',
      4 => 'Contact',
    ),
    'fitness' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Programs',
      3 => 'Membership',
      4 => 'Contact',
    ),
    'real-estate' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Properties',
      3 => 'Agents',
      4 => 'Contact',
    ),
    'hotel' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Rooms',
      3 => 'Gallery',
      4 => 'Book Now',
    ),
    'travel' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Destinations',
      3 => 'Tour Packages',
      4 => 'Contact',
    ),
    'technology' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Solutions',
      3 => 'Case Studies',
      4 => 'Contact',
    ),
    'education' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Courses',
      3 => 'Admissions',
      4 => 'Contact',
    ),
    'finance' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Services',
      3 => 'Resources',
      4 => 'Contact',
    ),
    'electrician' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Services',
      3 => 'Projects',
      4 => 'Contact',
    ),
    'plumbing' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Services',
      3 => 'Emergency Service',
      4 => 'Contact',
    ),
    'cleaning' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Services',
      3 => 'Pricing',
      4 => 'Contact',
    ),
    'landscaping' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Services',
      3 => 'Projects',
      4 => 'Contact',
    ),
    'automotive' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Services',
      3 => 'Gallery',
      4 => 'Book Service',
    ),
    'salon' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Services',
      3 => 'Gallery',
      4 => 'Book Appointment',
    ),
    'default' => 
    array (
      0 => 'Home',
      1 => 'About',
      2 => 'Services',
      3 => 'Contact',
    ),
  ),
  'view' => 
  array (
    'paths' => 
    array (
      0 => 'C:\\xampp\\htdocs\\my-custom-cms\\resources\\views',
    ),
    'compiled' => 'C:\\xampp\\htdocs\\my-custom-cms\\storage\\framework\\views',
  ),
  'workspace_roles' => 
  array (
    'roles' => 
    array (
      'owner' => 
      array (
        'label' => 'Owner',
        'description' => 'Full workspace control, including billing, team, websites, and ownership.',
        'permissions' => 
        array (
          0 => '*',
        ),
      ),
      'admin' => 
      array (
        'label' => 'Admin',
        'description' => 'Manages websites, content, publishing, insights, and clients without billing or ownership access.',
        'permissions' => 
        array (
          0 => 'workspace.view',
          1 => 'websites.create',
          2 => 'websites.view',
          3 => 'websites.edit',
          4 => 'websites.delete',
          5 => 'websites.duplicate',
          6 => 'pages.edit',
          7 => 'pages.publish',
          8 => 'ai.use',
          9 => 'sparks.manage',
          10 => 'analytics.view',
          11 => 'leads.view',
          12 => 'leads.manage',
          13 => 'sales.view',
          14 => 'clients.manage',
        ),
      ),
      'editor' => 
      array (
        'label' => 'Editor',
        'description' => 'Creates and edits website content, uses AI tools, and saves drafts.',
        'permissions' => 
        array (
          0 => 'workspace.view',
          1 => 'websites.view',
          2 => 'websites.edit',
          3 => 'pages.edit',
          4 => 'ai.use',
          5 => 'sparks.use',
        ),
      ),
      'client' => 
      array (
        'label' => 'Client',
        'description' => 'Reviews assigned work and approved reporting without editing workspace content.',
        'permissions' => 
        array (
          0 => 'workspace.view',
          1 => 'websites.view',
          2 => 'analytics.view',
          3 => 'leads.view',
        ),
      ),
    ),
    'permission_labels' => 
    array (
      'workspace.view' => 'View workspace',
      'workspace.manage' => 'Manage workspace',
      'billing.manage' => 'Manage billing',
      'team.manage' => 'Manage team',
      'websites.create' => 'Create websites',
      'websites.view' => 'View websites',
      'websites.edit' => 'Edit websites',
      'websites.delete' => 'Delete websites',
      'websites.duplicate' => 'Duplicate websites',
      'pages.edit' => 'Edit pages',
      'pages.publish' => 'Publish pages',
      'ai.use' => 'Use AI generation',
      'sparks.use' => 'Use Sparks',
      'sparks.manage' => 'Manage Sparks',
      'analytics.view' => 'View analytics',
      'leads.view' => 'View leads',
      'leads.manage' => 'Manage leads',
      'sales.view' => 'View sales',
      'clients.manage' => 'Manage clients',
      'workspace.transfer' => 'Transfer workspace ownership',
    ),
  ),
  'inertia' => 
  array (
    'ssr' => 
    array (
      'enabled' => true,
      'url' => 'http://127.0.0.1:13714',
      'ensure_bundle_exists' => true,
    ),
    'ensure_pages_exist' => false,
    'page_paths' => 
    array (
      0 => 'C:\\xampp\\htdocs\\my-custom-cms\\resources\\js/Pages',
    ),
    'page_extensions' => 
    array (
      0 => 'js',
      1 => 'jsx',
      2 => 'svelte',
      3 => 'ts',
      4 => 'tsx',
      5 => 'vue',
    ),
    'use_script_element_for_initial_page' => false,
    'testing' => 
    array (
      'ensure_pages_exist' => true,
      'page_paths' => 
      array (
        0 => 'C:\\xampp\\htdocs\\my-custom-cms\\resources\\js/Pages',
      ),
      'page_extensions' => 
      array (
        0 => 'js',
        1 => 'jsx',
        2 => 'svelte',
        3 => 'ts',
        4 => 'tsx',
        5 => 'vue',
      ),
    ),
    'history' => 
    array (
      'encrypt' => false,
    ),
  ),
  'tinker' => 
  array (
    'commands' => 
    array (
    ),
    'alias' => 
    array (
    ),
    'dont_alias' => 
    array (
      0 => 'App\\Nova',
    ),
    'trust_project' => 'always',
  ),
);
