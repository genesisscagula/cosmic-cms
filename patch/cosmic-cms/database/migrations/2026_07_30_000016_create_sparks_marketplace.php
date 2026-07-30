<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('spark_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sparks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spark_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('layout_json')->nullable();
            $table->unsignedInteger('credits')->default(100);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_sparks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spark_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('credits_paid')->default(0);
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'spark_id']);
        });

        $now = now();
        $categories = [
            ['name' => 'Landing Pages', 'slug' => 'landing', 'sort_order' => 10],
            ['name' => 'About', 'slug' => 'about', 'sort_order' => 20],
            ['name' => 'Services', 'slug' => 'services', 'sort_order' => 30],
            ['name' => 'Team', 'slug' => 'team', 'sort_order' => 40],
            ['name' => 'Pricing', 'slug' => 'pricing', 'sort_order' => 50],
            ['name' => 'Portfolio', 'slug' => 'portfolio', 'sort_order' => 60],
            ['name' => 'Contact', 'slug' => 'contact', 'sort_order' => 70],
            ['name' => 'FAQ', 'slug' => 'faq', 'sort_order' => 80],
            ['name' => 'Case Studies', 'slug' => 'case-studies', 'sort_order' => 90],
        ];
        foreach ($categories as &$category) { $category['created_at'] = $now; $category['updated_at'] = $now; }
        DB::table('spark_categories')->insert($categories);
        $categoryIds = DB::table('spark_categories')->pluck('id', 'slug');

        $sparks = [
            ['name' => 'Launchpad', 'category' => 'landing', 'description' => 'A polished conversion-first landing page with hero, proof, services and CTA.', 'credits' => 100, 'featured' => true],
            ['name' => 'Founder Story', 'category' => 'about', 'description' => 'Tell your story with a confident narrative, values, milestones and trust signals.', 'credits' => 100, 'featured' => true],
            ['name' => 'Service Studio', 'category' => 'services', 'description' => 'A clear service catalogue with benefits, process and a strong enquiry path.', 'credits' => 100, 'featured' => true],
            ['name' => 'Meet the Team', 'category' => 'team', 'description' => 'A human team page with leadership profiles, culture and hiring CTA.', 'credits' => 100, 'featured' => false],
            ['name' => 'Simple Pricing', 'category' => 'pricing', 'description' => 'Clean pricing tiers, comparison points, FAQs and conversion-focused CTA.', 'credits' => 120, 'featured' => true],
            ['name' => 'Selected Work', 'category' => 'portfolio', 'description' => 'Showcase projects with visual case highlights, results and testimonials.', 'credits' => 120, 'featured' => true],
            ['name' => 'Contact Flow', 'category' => 'contact', 'description' => 'A focused contact experience with enquiry form, details and next steps.', 'credits' => 100, 'featured' => false],
            ['name' => 'Answer Centre', 'category' => 'faq', 'description' => 'Organised FAQs with trust-building copy and a final support CTA.', 'credits' => 100, 'featured' => false],
            ['name' => 'Proof in Practice', 'category' => 'case-studies', 'description' => 'A premium case studies index designed around challenges, outcomes and proof.', 'credits' => 120, 'featured' => true],
        ];

        foreach ($sparks as $index => $spark) {
            DB::table('sparks')->insert([
                'spark_category_id' => $categoryIds[$spark['category']],
                'name' => $spark['name'],
                'slug' => Str::slug($spark['name']),
                'description' => $spark['description'],
                'layout_json' => json_encode([]),
                'credits' => $spark['credits'],
                'is_featured' => $spark['featured'],
                'is_published' => true,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sparks');
        Schema::dropIfExists('sparks');
        Schema::dropIfExists('spark_categories');
    }
};
