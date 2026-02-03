<?php

namespace Database\Seeders;

use App\Enums\FieldType;
use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Enums\PublishStatus;
use App\Enums\CalendarEventType;
use App\Models\Base;
use App\Models\Board;
use App\Models\BoardCard;
use App\Models\BoardColumn;
use App\Models\Brand;
use App\Models\BrandMember;
use App\Models\CalendarEvent;
use App\Models\Notification;
use App\Models\PlatformPost;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    private User $admin;
    private Brand $brand;

    /**
     * Create admin account with full example data for testing.
     *
     * Run with: php artisan db:seed --class=AdminSeeder
     */
    public function run(): void
    {
        $this->command->info('Creating admin account with example data...');

        $this->createAdmin();
        $this->createBrand();
        $this->createSocialPosts();
        $this->createDatabases();
        $this->createBoards();
        $this->createCalendarEvents();
        $this->createNotifications();

        $this->command->newLine();
        $this->command->info('Admin account created successfully!');
        $this->command->newLine();
        $this->command->info('Login credentials:');
        $this->command->info('  Email: admin@admin.com');
        $this->command->info('  Password: password');
    }

    private function createAdmin(): void
    {
        $this->admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('toNieMojeHaslo'),
                'email_verified_at' => now(),
                'is_admin' => true,
            ]
        );

        // Ensure is_admin is set even if user already existed
        if (!$this->admin->is_admin) {
            $this->admin->update(['is_admin' => true]);
        }

        $this->command->info('Admin user created.');
    }

    private function createBrand(): void
    {
        $this->brand = Brand::firstOrCreate(
            ['user_id' => $this->admin->id, 'name' => 'Personal Brand'],
            [
                'industry' => 'marketing',
                'description' => 'Personal branding & marketing automation agency. We help entrepreneurs build strong personal brands through consistent content creation and social media presence.',
                'target_audience' => [
                    'age_range' => '25-45',
                    'gender' => 'all',
                    'interests' => ['marketing', 'entrepreneurship', 'personal development', 'social media', 'branding'],
                    'pain_points' => ['lack of consistency', 'no time for content creation', 'low engagement', 'unclear brand message'],
                ],
                'voice' => [
                    'tone' => 'professional',
                    'personality' => ['expert', 'approachable', 'motivational'],
                    'language' => 'pl',
                    'emoji_usage' => 'sometimes',
                ],
                'content_pillars' => [
                    ['name' => 'Tips & Tricks', 'description' => 'Practical marketing tips', 'percentage' => 30],
                    ['name' => 'Case Studies', 'description' => 'Success stories and analyses', 'percentage' => 25],
                    ['name' => 'Behind the Scenes', 'description' => 'Day-to-day work insights', 'percentage' => 20],
                    ['name' => 'Industry News', 'description' => 'Latest trends and updates', 'percentage' => 15],
                    ['name' => 'Motivation', 'description' => 'Inspirational content', 'percentage' => 10],
                ],
                'posting_preferences' => [
                    'frequency' => [
                        'facebook' => 5,
                        'instagram' => 7,
                        'youtube' => 2,
                    ],
                    'best_times' => [
                        'facebook' => ['09:00', '12:00', '18:00'],
                        'instagram' => ['08:00', '12:00', '17:00', '20:00'],
                        'youtube' => ['10:00', '15:00'],
                    ],
                    'auto_schedule' => true,
                ],
                'platforms' => [
                    'facebook' => ['enabled' => true, 'page_name' => 'Personal Brand'],
                    'instagram' => ['enabled' => true, 'handle' => '@personalbrand'],
                    'youtube' => ['enabled' => true, 'channel' => 'Personal Brand TV'],
                ],
                'onboarding_completed' => true,
                'is_active' => true,
                'automation_enabled' => true,
                'content_queue_days' => 14,
                'automation_settings' => [
                    'auto_generate_captions' => true,
                    'auto_suggest_hashtags' => true,
                    'auto_schedule_posts' => true,
                ],
            ]
        );

        // Create brand membership (owner)
        BrandMember::firstOrCreate(
            ['brand_id' => $this->brand->id, 'user_id' => $this->admin->id],
            [
                'role' => 'owner',
                'accepted_at' => now(),
            ]
        );

        // Set current brand for the user
        $this->admin->setSetting('current_brand_id', $this->brand->id);

        $this->command->info('Brand "Personal Brand" created.');
    }

    private function createSocialPosts(): void
    {
        $posts = [
            [
                'title' => '5 Mistakes in Personal Branding',
                'main_caption' => "Building a personal brand? Avoid these 5 common mistakes that hold most people back.\n\n1. No consistency in posting\n2. Copying others instead of being authentic\n3. Ignoring analytics and data\n4. Not engaging with your audience\n5. Trying to be everywhere at once\n\nWhich mistake have you made? Share in the comments!\n\n#PersonalBranding #Marketing #ContentCreation",
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(7),
                'scheduled_at' => now()->subDays(7),
                'position' => 1,
            ],
            [
                'title' => 'How I Grew to 10k Followers',
                'main_caption' => "From 0 to 10k followers in 6 months. Here's exactly what I did:\n\nWeek 1-4: Posted daily, found my niche\nMonth 2: Started collaborating with other creators\nMonth 3: Launched my first lead magnet\nMonth 4: Consistent Reels + Stories strategy\nMonth 5-6: Scaled what worked, dropped what didn't\n\nThe secret? There's no secret. Just consistency and value.\n\n#GrowthStrategy #SocialMediaMarketing #ContentStrategy",
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(5),
                'scheduled_at' => now()->subDays(5),
                'position' => 2,
            ],
            [
                'title' => 'Content Calendar Template',
                'main_caption' => "Stop planning your content randomly. Use this simple framework:\n\nMonday: Educational content (tips, tutorials)\nTuesday: Storytelling (personal experience)\nWednesday: Industry news & commentary\nThursday: Behind the scenes\nFriday: Engagement post (polls, questions)\nSaturday: Motivation & inspiration\nSunday: Rest or repurpose top content\n\nSave this for later!\n\n#ContentCalendar #ContentPlanning #MarketingTips",
                'status' => PostStatus::Scheduled,
                'scheduled_at' => now()->addDays(2),
                'position' => 3,
            ],
            [
                'title' => 'AI Tools for Content Creation',
                'main_caption' => "AI won't replace creators. But creators who use AI will replace those who don't.\n\nHere are my top 5 AI tools for content creation:\n\n1. ChatGPT - brainstorming & copywriting\n2. Midjourney - visual content\n3. Descript - video editing\n4. Buffer - scheduling & analytics\n5. Canva AI - quick designs\n\nWhich tools do you use? Let me know!\n\n#AITools #ContentCreation #MarTech",
                'status' => PostStatus::Scheduled,
                'scheduled_at' => now()->addDays(4),
                'position' => 4,
            ],
            [
                'title' => 'Client Success Story - TechStartup',
                'main_caption' => "Case study: How TechStartup grew their LinkedIn presence by 300% in 3 months.\n\nThe challenge:\n- Low engagement\n- Inconsistent posting\n- No clear brand voice\n\nOur solution:\n- Defined brand personality & tone\n- Created content pillars strategy\n- Implemented posting schedule\n- Engaged with community daily\n\nResults:\n- 300% follower growth\n- 5x more engagement\n- 12 inbound leads per month\n\n#CaseStudy #LinkedInMarketing #BrandStrategy",
                'status' => PostStatus::Draft,
                'position' => 5,
            ],
            [
                'title' => 'The Power of Storytelling',
                'main_caption' => "Facts tell, stories sell.\n\nWhy storytelling is the most powerful marketing tool:\n\n- People remember stories 22x more than facts alone\n- Stories create emotional connections\n- They make your brand relatable and human\n- Stories drive action through empathy\n\nStart every post with a story. Watch your engagement skyrocket.\n\n#Storytelling #MarketingStrategy #BrandBuilding",
                'status' => PostStatus::Draft,
                'position' => 6,
            ],
            [
                'title' => 'Monday Motivation',
                'main_caption' => "New week, new opportunities.\n\nRemember: Your personal brand is built one post at a time. Every piece of content is a brick in your digital empire.\n\nDon't wait for perfection. Start messy, iterate, and improve.\n\nWhat's your goal for this week? Drop it below!\n\n#MondayMotivation #PersonalBrand #Entrepreneurship",
                'status' => PostStatus::PendingApproval,
                'position' => 7,
            ],
            [
                'title' => 'Behind the Scenes - Our Process',
                'main_caption' => "Ever wonder how we create content for our clients? Here's a peek behind the curtain:\n\n1. Strategy session (understanding the brand)\n2. Content pillar definition\n3. Weekly content calendar\n4. Batch content creation\n5. Review & approval workflow\n6. Scheduling & publishing\n7. Analytics & optimization\n\nWe treat content like a system, not a task.\n\n#BehindTheScenes #ContentAgency #WorkProcess",
                'status' => PostStatus::Approved,
                'scheduled_at' => now()->addDays(1),
                'position' => 8,
            ],
        ];

        foreach ($posts as $postData) {
            $post = SocialPost::firstOrCreate(
                ['user_id' => $this->admin->id, 'title' => $postData['title']],
                array_merge($postData, ['brand_id' => $this->brand->id])
            );

            // Create platform posts for each social post
            foreach (Platform::cases() as $platform) {
                PlatformPost::firstOrCreate(
                    ['social_post_id' => $post->id, 'platform' => $platform->value],
                    [
                        'enabled' => true,
                        'publish_status' => match ($post->status) {
                            PostStatus::Published => PublishStatus::Published,
                            PostStatus::Failed => PublishStatus::Failed,
                            default => PublishStatus::NotStarted,
                        },
                        'published_at' => $post->status === PostStatus::Published ? $post->published_at : null,
                    ]
                );
            }
        }

        $this->command->info('Social posts created (' . count($posts) . ' posts).');
    }

    private function createDatabases(): void
    {
        $this->createClientsDatabase();
        $this->createContentDatabase();
    }

    private function createClientsDatabase(): void
    {
        $base = Base::firstOrCreate(
            ['user_id' => $this->admin->id, 'name' => 'Clients & Leads'],
            [
                'description' => 'Client management and lead tracking',
                'color' => '#3B82F6',
                'icon' => '👥',
            ]
        );

        // ========== CLIENTS TABLE ==========
        $clientsTable = $base->tables()->firstOrCreate(
            ['name' => 'Clients'],
            ['description' => 'Active and potential clients']
        );

        if ($clientsTable->fields()->count() === 0) {
            $fields = [];
            $fields['name'] = $clientsTable->fields()->create([
                'name' => 'Name',
                'type' => FieldType::TEXT,
                'is_primary' => true,
            ]);
            $fields['email'] = $clientsTable->fields()->create([
                'name' => 'Email',
                'type' => FieldType::TEXT,
            ]);
            $fields['company'] = $clientsTable->fields()->create([
                'name' => 'Company',
                'type' => FieldType::TEXT,
            ]);
            $fields['status'] = $clientsTable->fields()->create([
                'name' => 'Status',
                'type' => FieldType::SELECT,
                'options' => [
                    'choices' => [
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Lead', 'color' => '#6B7280'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Contacted', 'color' => '#F59E0B'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Negotiation', 'color' => '#3B82F6'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Active Client', 'color' => '#10B981'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Churned', 'color' => '#EF4444'],
                    ],
                ],
            ]);
            $fields['package'] = $clientsTable->fields()->create([
                'name' => 'Package',
                'type' => FieldType::SELECT,
                'options' => [
                    'choices' => [
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Starter', 'color' => '#10B981'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Growth', 'color' => '#3B82F6'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Premium', 'color' => '#8B5CF6'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Enterprise', 'color' => '#F59E0B'],
                    ],
                ],
            ]);
            $fields['mrr'] = $clientsTable->fields()->create([
                'name' => 'MRR (PLN)',
                'type' => FieldType::NUMBER,
            ]);
            $fields['platforms'] = $clientsTable->fields()->create([
                'name' => 'Platforms',
                'type' => FieldType::MULTI_SELECT,
                'options' => [
                    'choices' => [
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Instagram', 'color' => '#E4405F'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Facebook', 'color' => '#1877F2'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'YouTube', 'color' => '#FF0000'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'LinkedIn', 'color' => '#0077B5'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'TikTok', 'color' => '#000000'],
                    ],
                ],
            ]);
            $fields['start_date'] = $clientsTable->fields()->create([
                'name' => 'Start Date',
                'type' => FieldType::DATE,
            ]);
            $fields['website'] = $clientsTable->fields()->create([
                'name' => 'Website',
                'type' => FieldType::URL,
            ]);
            $fields['active'] = $clientsTable->fields()->create([
                'name' => 'Active',
                'type' => FieldType::CHECKBOX,
            ]);
            $fields['notes'] = $clientsTable->fields()->create([
                'name' => 'Notes',
                'type' => FieldType::TEXT,
            ]);

            $clients = [
                ['name' => 'Maria Wisniewska', 'email' => 'maria@coaching.pl', 'company' => 'Life Coaching Pro', 'status' => 'Active Client', 'package' => 'Premium', 'mrr' => 4500, 'platforms' => ['Instagram', 'Facebook', 'LinkedIn'], 'start_date' => '2025-09-01', 'website' => 'https://lifecoachingpro.pl', 'active' => true, 'notes' => 'Very engaged. Wants to expand to YouTube next quarter.'],
                ['name' => 'Tomasz Kaczmarek', 'email' => 'tomek@fitbody.pl', 'company' => 'FitBody Studio', 'status' => 'Active Client', 'package' => 'Growth', 'mrr' => 2500, 'platforms' => ['Instagram', 'TikTok', 'YouTube'], 'start_date' => '2025-11-15', 'website' => 'https://fitbody.pl', 'active' => true, 'notes' => 'Fitness niche. Great video content potential.'],
                ['name' => 'Agnieszka Nowak', 'email' => 'agnieszka@lawfirm.pl', 'company' => 'Nowak Legal', 'status' => 'Active Client', 'package' => 'Starter', 'mrr' => 1500, 'platforms' => ['LinkedIn', 'Facebook'], 'start_date' => '2026-01-10', 'website' => 'https://nowaklegal.pl', 'active' => true, 'notes' => 'New client. Legal niche - needs professional tone.'],
                ['name' => 'Pawel Zielinski', 'email' => 'pawel@techstartup.io', 'company' => 'TechStartup.io', 'status' => 'Negotiation', 'package' => 'Enterprise', 'mrr' => 8000, 'platforms' => ['LinkedIn', 'YouTube', 'Instagram'], 'start_date' => '', 'website' => 'https://techstartup.io', 'active' => false, 'notes' => 'Big potential. Needs full brand strategy + content production.'],
                ['name' => 'Katarzyna Lewandowska', 'email' => 'kasia@beauty.com', 'company' => 'Beauty By Kasia', 'status' => 'Active Client', 'package' => 'Growth', 'mrr' => 3000, 'platforms' => ['Instagram', 'TikTok', 'Facebook'], 'start_date' => '2025-10-01', 'website' => 'https://beautybykasia.com', 'active' => true, 'notes' => 'Beauty niche. High engagement rates.'],
                ['name' => 'Marek Jankowski', 'email' => 'marek@investment.pl', 'company' => 'Smart Invest', 'status' => 'Contacted', 'package' => '', 'mrr' => 0, 'platforms' => ['LinkedIn'], 'start_date' => '', 'website' => 'https://smartinvest.pl', 'active' => false, 'notes' => 'Interested in LinkedIn strategy. Follow up next week.'],
                ['name' => 'Ola Kaminska', 'email' => 'ola@designstudio.pl', 'company' => 'Design Studio OK', 'status' => 'Lead', 'package' => '', 'mrr' => 0, 'platforms' => ['Instagram'], 'start_date' => '', 'website' => 'https://designstudiook.pl', 'active' => false, 'notes' => 'Inbound from website. Sent proposal.'],
                ['name' => 'Robert Mazur', 'email' => 'robert@realestate.pl', 'company' => 'Mazur Properties', 'status' => 'Churned', 'package' => 'Starter', 'mrr' => 0, 'platforms' => ['Facebook', 'Instagram'], 'start_date' => '2025-06-01', 'website' => 'https://mazurproperties.pl', 'active' => false, 'notes' => 'Cancelled after 3 months. Budget cuts.'],
            ];

            foreach ($clients as $data) {
                $row = $clientsTable->rows()->create();
                $this->setCellValues($row, $fields, $data);
            }
        }

        // ========== INVOICES TABLE ==========
        $invoicesTable = $base->tables()->firstOrCreate(
            ['name' => 'Invoices'],
            ['description' => 'Invoice tracking']
        );

        if ($invoicesTable->fields()->count() === 0) {
            $fields = [];
            $fields['number'] = $invoicesTable->fields()->create([
                'name' => 'Invoice #',
                'type' => FieldType::TEXT,
                'is_primary' => true,
            ]);
            $fields['client'] = $invoicesTable->fields()->create([
                'name' => 'Client',
                'type' => FieldType::TEXT,
            ]);
            $fields['amount'] = $invoicesTable->fields()->create([
                'name' => 'Amount (PLN)',
                'type' => FieldType::NUMBER,
            ]);
            $fields['status'] = $invoicesTable->fields()->create([
                'name' => 'Status',
                'type' => FieldType::SELECT,
                'options' => [
                    'choices' => [
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Draft', 'color' => '#6B7280'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Sent', 'color' => '#3B82F6'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Paid', 'color' => '#10B981'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Overdue', 'color' => '#EF4444'],
                    ],
                ],
            ]);
            $fields['issue_date'] = $invoicesTable->fields()->create([
                'name' => 'Issue Date',
                'type' => FieldType::DATE,
            ]);
            $fields['due_date'] = $invoicesTable->fields()->create([
                'name' => 'Due Date',
                'type' => FieldType::DATE,
            ]);
            $fields['paid'] = $invoicesTable->fields()->create([
                'name' => 'Paid',
                'type' => FieldType::CHECKBOX,
            ]);

            $invoices = [
                ['number' => 'INV-2026-001', 'client' => 'Life Coaching Pro', 'amount' => 4500, 'status' => 'Paid', 'issue_date' => '2026-01-01', 'due_date' => '2026-01-15', 'paid' => true],
                ['number' => 'INV-2026-002', 'client' => 'FitBody Studio', 'amount' => 2500, 'status' => 'Paid', 'issue_date' => '2026-01-01', 'due_date' => '2026-01-15', 'paid' => true],
                ['number' => 'INV-2026-003', 'client' => 'Beauty By Kasia', 'amount' => 3000, 'status' => 'Paid', 'issue_date' => '2026-01-01', 'due_date' => '2026-01-15', 'paid' => true],
                ['number' => 'INV-2026-004', 'client' => 'Nowak Legal', 'amount' => 1500, 'status' => 'Sent', 'issue_date' => '2026-01-10', 'due_date' => '2026-01-24', 'paid' => false],
                ['number' => 'INV-2026-005', 'client' => 'Life Coaching Pro', 'amount' => 4500, 'status' => 'Draft', 'issue_date' => '2026-02-01', 'due_date' => '2026-02-15', 'paid' => false],
                ['number' => 'INV-2026-006', 'client' => 'FitBody Studio', 'amount' => 2500, 'status' => 'Sent', 'issue_date' => '2026-02-01', 'due_date' => '2026-02-15', 'paid' => false],
            ];

            foreach ($invoices as $data) {
                $row = $invoicesTable->rows()->create();
                $this->setCellValues($row, $fields, $data);
            }
        }

        $this->command->info('Clients & Leads database created.');
    }

    private function createContentDatabase(): void
    {
        $base = Base::firstOrCreate(
            ['user_id' => $this->admin->id, 'name' => 'Content Library'],
            [
                'description' => 'Content ideas, hashtags and templates tracking',
                'color' => '#8B5CF6',
                'icon' => '📝',
            ]
        );

        // ========== CONTENT IDEAS TABLE ==========
        $ideasTable = $base->tables()->firstOrCreate(
            ['name' => 'Content Ideas'],
            ['description' => 'Backlog of content ideas']
        );

        if ($ideasTable->fields()->count() === 0) {
            $fields = [];
            $fields['title'] = $ideasTable->fields()->create([
                'name' => 'Title',
                'type' => FieldType::TEXT,
                'is_primary' => true,
            ]);
            $fields['pillar'] = $ideasTable->fields()->create([
                'name' => 'Content Pillar',
                'type' => FieldType::SELECT,
                'options' => [
                    'choices' => [
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Tips & Tricks', 'color' => '#3B82F6'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Case Studies', 'color' => '#10B981'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Behind the Scenes', 'color' => '#F59E0B'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Industry News', 'color' => '#8B5CF6'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Motivation', 'color' => '#EF4444'],
                    ],
                ],
            ]);
            $fields['format'] = $ideasTable->fields()->create([
                'name' => 'Format',
                'type' => FieldType::SELECT,
                'options' => [
                    'choices' => [
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Carousel', 'color' => '#3B82F6'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Reel/Short', 'color' => '#EF4444'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Story', 'color' => '#F59E0B'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Single Image', 'color' => '#10B981'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Video', 'color' => '#8B5CF6'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Text Post', 'color' => '#6B7280'],
                    ],
                ],
            ]);
            $fields['status'] = $ideasTable->fields()->create([
                'name' => 'Status',
                'type' => FieldType::SELECT,
                'options' => [
                    'choices' => [
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Idea', 'color' => '#6B7280'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'In Production', 'color' => '#3B82F6'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Ready', 'color' => '#10B981'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Published', 'color' => '#8B5CF6'],
                    ],
                ],
            ]);
            $fields['platform'] = $ideasTable->fields()->create([
                'name' => 'Target Platform',
                'type' => FieldType::MULTI_SELECT,
                'options' => [
                    'choices' => [
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Instagram', 'color' => '#E4405F'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Facebook', 'color' => '#1877F2'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'YouTube', 'color' => '#FF0000'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'LinkedIn', 'color' => '#0077B5'],
                    ],
                ],
            ]);
            $fields['notes'] = $ideasTable->fields()->create([
                'name' => 'Notes',
                'type' => FieldType::TEXT,
            ]);

            $ideas = [
                ['title' => '10 Tools Every Creator Needs', 'pillar' => 'Tips & Tricks', 'format' => 'Carousel', 'status' => 'Ready', 'platform' => ['Instagram', 'LinkedIn'], 'notes' => 'List with screenshots of each tool'],
                ['title' => 'A Day in My Life as a Brand Strategist', 'pillar' => 'Behind the Scenes', 'format' => 'Reel/Short', 'status' => 'In Production', 'platform' => ['Instagram', 'YouTube'], 'notes' => 'Film throughout the day, edit as fast-paced montage'],
                ['title' => 'How We Tripled Engagement for a Client', 'pillar' => 'Case Studies', 'format' => 'Carousel', 'status' => 'Idea', 'platform' => ['Instagram', 'LinkedIn'], 'notes' => 'Use real data (anonymized)'],
                ['title' => 'Instagram Algorithm Update 2026', 'pillar' => 'Industry News', 'format' => 'Single Image', 'status' => 'Idea', 'platform' => ['Instagram', 'Facebook'], 'notes' => 'React to latest changes'],
                ['title' => 'Why Most Brands Fail on Social Media', 'pillar' => 'Tips & Tricks', 'format' => 'Video', 'status' => 'Idea', 'platform' => ['YouTube', 'LinkedIn'], 'notes' => 'Talking head + B-roll of examples'],
                ['title' => 'The 80/20 Rule of Content Creation', 'pillar' => 'Tips & Tricks', 'format' => 'Carousel', 'status' => 'Published', 'platform' => ['Instagram', 'Facebook', 'LinkedIn'], 'notes' => 'Focus on value vs promotional content ratio'],
                ['title' => 'Start Before You Are Ready', 'pillar' => 'Motivation', 'format' => 'Single Image', 'status' => 'Published', 'platform' => ['Instagram'], 'notes' => 'Quote graphic with brand colors'],
                ['title' => 'Client Onboarding Process Walkthrough', 'pillar' => 'Behind the Scenes', 'format' => 'Reel/Short', 'status' => 'In Production', 'platform' => ['Instagram', 'YouTube'], 'notes' => 'Screen recording + voiceover'],
                ['title' => 'Hashtag Strategy That Actually Works', 'pillar' => 'Tips & Tricks', 'format' => 'Carousel', 'status' => 'Idea', 'platform' => ['Instagram'], 'notes' => '3-bucket strategy: niche, community, broad'],
                ['title' => 'Monthly Wrap-Up: January 2026', 'pillar' => 'Behind the Scenes', 'format' => 'Text Post', 'status' => 'Ready', 'platform' => ['LinkedIn', 'Facebook'], 'notes' => 'Stats, lessons learned, plans for February'],
            ];

            foreach ($ideas as $data) {
                $row = $ideasTable->rows()->create();
                $this->setCellValues($row, $fields, $data);
            }
        }

        // ========== HASHTAGS TABLE ==========
        $hashtagsTable = $base->tables()->firstOrCreate(
            ['name' => 'Hashtag Sets'],
            ['description' => 'Reusable hashtag groups']
        );

        if ($hashtagsTable->fields()->count() === 0) {
            $fields = [];
            $fields['name'] = $hashtagsTable->fields()->create([
                'name' => 'Set Name',
                'type' => FieldType::TEXT,
                'is_primary' => true,
            ]);
            $fields['hashtags'] = $hashtagsTable->fields()->create([
                'name' => 'Hashtags',
                'type' => FieldType::TEXT,
            ]);
            $fields['category'] = $hashtagsTable->fields()->create([
                'name' => 'Category',
                'type' => FieldType::SELECT,
                'options' => [
                    'choices' => [
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Branding', 'color' => '#3B82F6'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Marketing', 'color' => '#10B981'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Motivation', 'color' => '#F59E0B'],
                        ['id' => Str::ulid()->toBase32(), 'name' => 'Industry', 'color' => '#8B5CF6'],
                    ],
                ],
            ]);
            $fields['reach'] = $hashtagsTable->fields()->create([
                'name' => 'Avg Reach',
                'type' => FieldType::NUMBER,
            ]);

            $hashtags = [
                ['name' => 'Personal Branding Core', 'hashtags' => '#PersonalBranding #PersonalBrand #BrandYourself #PersonalGrowth #BrandIdentity', 'category' => 'Branding', 'reach' => 15000],
                ['name' => 'Content Marketing', 'hashtags' => '#ContentMarketing #ContentCreation #ContentStrategy #DigitalMarketing #MarketingTips', 'category' => 'Marketing', 'reach' => 22000],
                ['name' => 'Social Media Growth', 'hashtags' => '#SocialMediaMarketing #GrowYourBrand #SocialMediaTips #InstagramGrowth #LinkedInTips', 'category' => 'Marketing', 'reach' => 18000],
                ['name' => 'Entrepreneurship', 'hashtags' => '#Entrepreneurship #BusinessOwner #StartupLife #HustleAndGrind #CEO', 'category' => 'Motivation', 'reach' => 30000],
                ['name' => 'Polish Market', 'hashtags' => '#MarketingPolska #BiznesOnline #MarkiOsobiste #SocialMediaPolska #ContentCreatorPL', 'category' => 'Industry', 'reach' => 5000],
            ];

            foreach ($hashtags as $data) {
                $row = $hashtagsTable->rows()->create();
                $this->setCellValues($row, $fields, $data);
            }
        }

        $this->command->info('Content Library database created.');
    }

    private function createBoards(): void
    {
        // ========== CONTENT PRODUCTION BOARD ==========
        $contentBoard = Board::firstOrCreate(
            ['brand_id' => $this->brand->id, 'name' => 'Content Production'],
            [
                'description' => 'Track content from idea to publication',
                'color' => '#3B82F6',
                'settings' => ['card_cover_enabled' => true],
            ]
        );

        if ($contentBoard->columns()->count() === 0) {
            $backlog = $contentBoard->columns()->create(['name' => 'Backlog', 'color' => '#6B7280', 'position' => 0]);
            $research = $contentBoard->columns()->create(['name' => 'Research', 'color' => '#8B5CF6', 'position' => 1, 'card_limit' => 3]);
            $writing = $contentBoard->columns()->create(['name' => 'Writing / Filming', 'color' => '#3B82F6', 'position' => 2, 'card_limit' => 3]);
            $design = $contentBoard->columns()->create(['name' => 'Design', 'color' => '#F59E0B', 'position' => 3, 'card_limit' => 3]);
            $review = $contentBoard->columns()->create(['name' => 'Review', 'color' => '#EF4444', 'position' => 4, 'card_limit' => 5]);
            $scheduled = $contentBoard->columns()->create(['name' => 'Scheduled', 'color' => '#0EA5E9', 'position' => 5]);
            $published = $contentBoard->columns()->create(['name' => 'Published', 'color' => '#10B981', 'position' => 6]);

            // Backlog cards
            BoardCard::create(['column_id' => $backlog->id, 'created_by' => $this->admin->id, 'title' => 'Instagram Algorithm Deep Dive', 'description' => 'Comprehensive post about 2026 algorithm changes and how to adapt', 'position' => 0, 'labels' => ['instagram', 'educational'], 'color' => '#E4405F']);
            BoardCard::create(['column_id' => $backlog->id, 'created_by' => $this->admin->id, 'title' => 'LinkedIn Growth Hacks', 'description' => '5 proven strategies to grow on LinkedIn organically', 'position' => 1, 'labels' => ['linkedin', 'tips']]);
            BoardCard::create(['column_id' => $backlog->id, 'created_by' => $this->admin->id, 'title' => 'Video Editing Tutorial', 'description' => 'Quick tutorial on editing Reels using mobile apps', 'position' => 2, 'labels' => ['tutorial', 'video']]);
            BoardCard::create(['column_id' => $backlog->id, 'created_by' => $this->admin->id, 'title' => 'Client Testimonial Compilation', 'description' => 'Gather and format client testimonials for social proof', 'position' => 3, 'labels' => ['social-proof']]);

            // Research
            BoardCard::create(['column_id' => $research->id, 'created_by' => $this->admin->id, 'title' => 'AI Content Creation Tools Comparison', 'description' => 'Compare ChatGPT, Claude, Gemini for content creation. Test each with same prompts.', 'position' => 0, 'labels' => ['ai', 'comparison'], 'due_date' => now()->addDays(3)]);
            BoardCard::create(['column_id' => $research->id, 'created_by' => $this->admin->id, 'title' => 'Social Media Trends Report Q1', 'description' => 'Research and compile Q1 2026 trends across all platforms', 'position' => 1, 'labels' => ['research', 'trends'], 'due_date' => now()->addDays(5)]);

            // Writing
            BoardCard::create(['column_id' => $writing->id, 'created_by' => $this->admin->id, 'title' => 'Client Case Study - FitBody', 'description' => "Write case study about FitBody Studio's Instagram growth. Include before/after metrics.", 'position' => 0, 'labels' => ['case-study'], 'color' => '#10B981', 'due_date' => now()->addDays(2)]);
            BoardCard::create(['column_id' => $writing->id, 'created_by' => $this->admin->id, 'title' => 'Email Newsletter - February', 'description' => 'Monthly newsletter: recap January wins, February content calendar preview', 'position' => 1, 'labels' => ['newsletter'], 'due_date' => now()->addDays(4)]);

            // Design
            BoardCard::create(['column_id' => $design->id, 'created_by' => $this->admin->id, 'title' => '5 Branding Mistakes Carousel', 'description' => 'Design 10-slide carousel post with brand colors. Each slide = one mistake + solution.', 'position' => 0, 'labels' => ['carousel', 'design'], 'color' => '#F59E0B', 'due_date' => now()->addDays(1)]);

            // Review
            BoardCard::create(['column_id' => $review->id, 'created_by' => $this->admin->id, 'title' => 'Content Calendar Template Post', 'description' => 'Final review before publishing. Check copy, hashtags, and image quality.', 'position' => 0, 'labels' => ['review'], 'due_date' => now()]);
            BoardCard::create(['column_id' => $review->id, 'created_by' => $this->admin->id, 'title' => 'Behind the Scenes Reel', 'description' => 'Review edited reel - check transitions, audio sync, captions', 'position' => 1, 'labels' => ['video', 'review'], 'due_date' => now()->addDays(1)]);

            // Scheduled
            BoardCard::create(['column_id' => $scheduled->id, 'created_by' => $this->admin->id, 'title' => 'Monday Motivation Quote', 'description' => 'Scheduled for next Monday 8:00 AM across all platforms', 'position' => 0, 'labels' => ['motivation'], 'color' => '#0EA5E9']);
            BoardCard::create(['column_id' => $scheduled->id, 'created_by' => $this->admin->id, 'title' => 'AI Tools for Content Creation', 'description' => 'Scheduled for Thursday 12:00 PM. All platforms.', 'position' => 1, 'labels' => ['ai', 'tips']]);

            // Published
            BoardCard::create(['column_id' => $published->id, 'created_by' => $this->admin->id, 'title' => '5 Mistakes in Personal Branding', 'description' => 'Published last week. Strong engagement - 450 likes, 89 comments.', 'position' => 0, 'labels' => ['published'], 'color' => '#10B981']);
            BoardCard::create(['column_id' => $published->id, 'created_by' => $this->admin->id, 'title' => '10k Followers Growth Story', 'description' => 'Published 5 days ago. Went semi-viral - 1.2k shares.', 'position' => 1, 'labels' => ['published', 'viral']]);
        }

        // ========== CLIENT MANAGEMENT BOARD ==========
        $clientBoard = Board::firstOrCreate(
            ['brand_id' => $this->brand->id, 'name' => 'Client Onboarding'],
            [
                'description' => 'Track client onboarding pipeline',
                'color' => '#10B981',
            ]
        );

        if ($clientBoard->columns()->count() === 0) {
            $inquiry = $clientBoard->columns()->create(['name' => 'New Inquiry', 'color' => '#6B7280', 'position' => 0]);
            $discovery = $clientBoard->columns()->create(['name' => 'Discovery Call', 'color' => '#3B82F6', 'position' => 1]);
            $proposal = $clientBoard->columns()->create(['name' => 'Proposal Sent', 'color' => '#F59E0B', 'position' => 2]);
            $onboarding = $clientBoard->columns()->create(['name' => 'Onboarding', 'color' => '#8B5CF6', 'position' => 3, 'card_limit' => 3]);
            $active = $clientBoard->columns()->create(['name' => 'Active Client', 'color' => '#10B981', 'position' => 4]);

            BoardCard::create(['column_id' => $inquiry->id, 'created_by' => $this->admin->id, 'title' => 'Ola Kaminska - Design Studio', 'description' => 'Inbound lead from website. Interested in Instagram management.', 'position' => 0, 'labels' => ['inbound'], 'due_date' => now()->addDays(1)]);
            BoardCard::create(['column_id' => $discovery->id, 'created_by' => $this->admin->id, 'title' => 'Marek Jankowski - Smart Invest', 'description' => 'LinkedIn strategy discussion. Call scheduled for Wednesday.', 'position' => 0, 'labels' => ['linkedin'], 'due_date' => now()->addDays(3)]);
            BoardCard::create(['column_id' => $proposal->id, 'created_by' => $this->admin->id, 'title' => 'Pawel Zielinski - TechStartup', 'description' => 'Enterprise proposal sent. Full brand strategy + content production. 8k PLN/mo.', 'position' => 0, 'labels' => ['enterprise', 'high-value'], 'color' => '#F59E0B', 'due_date' => now()->addDays(5)]);
            BoardCard::create(['column_id' => $onboarding->id, 'created_by' => $this->admin->id, 'title' => 'Agnieszka Nowak - Nowak Legal', 'description' => 'New client! Setting up brand strategy, content pillars, and posting schedule.', 'position' => 0, 'labels' => ['new-client'], 'color' => '#8B5CF6']);
            BoardCard::create(['column_id' => $active->id, 'created_by' => $this->admin->id, 'title' => 'Maria Wisniewska - Life Coaching', 'description' => 'Premium client since Sept 2025. Expanding to YouTube.', 'position' => 0, 'labels' => ['premium']]);
            BoardCard::create(['column_id' => $active->id, 'created_by' => $this->admin->id, 'title' => 'Tomasz Kaczmarek - FitBody', 'description' => 'Growth plan. Strong video content, great engagement rates.', 'position' => 1, 'labels' => ['growth']]);
            BoardCard::create(['column_id' => $active->id, 'created_by' => $this->admin->id, 'title' => 'Katarzyna Lewandowska - Beauty', 'description' => 'Growth plan since Oct 2025. Focus on Instagram & TikTok.', 'position' => 2, 'labels' => ['growth']]);
        }

        // ========== WEEKLY TASKS BOARD ==========
        $weeklyBoard = Board::firstOrCreate(
            ['brand_id' => $this->brand->id, 'name' => 'Weekly Tasks'],
            [
                'description' => 'Week-by-week task tracking',
                'color' => '#F59E0B',
            ]
        );

        if ($weeklyBoard->columns()->count() === 0) {
            $todo = $weeklyBoard->columns()->create(['name' => 'To Do', 'color' => '#6B7280', 'position' => 0]);
            $inProgress = $weeklyBoard->columns()->create(['name' => 'In Progress', 'color' => '#3B82F6', 'position' => 1, 'card_limit' => 5]);
            $done = $weeklyBoard->columns()->create(['name' => 'Done', 'color' => '#10B981', 'position' => 2]);

            BoardCard::create(['column_id' => $todo->id, 'created_by' => $this->admin->id, 'title' => 'Plan February content calendar', 'description' => 'Map out all posts for February across all client accounts', 'position' => 0, 'due_date' => now()->addDays(2)]);
            BoardCard::create(['column_id' => $todo->id, 'created_by' => $this->admin->id, 'title' => 'Record 3 Reels for Maria', 'description' => 'Life coaching tips series - batch record', 'position' => 1, 'due_date' => now()->addDays(3)]);
            BoardCard::create(['column_id' => $todo->id, 'created_by' => $this->admin->id, 'title' => 'Update hashtag research', 'description' => 'Refresh hashtag sets based on latest performance data', 'position' => 2, 'due_date' => now()->addDays(4)]);
            BoardCard::create(['column_id' => $todo->id, 'created_by' => $this->admin->id, 'title' => 'Send February invoices', 'description' => 'Generate and send invoices to all active clients', 'position' => 3, 'due_date' => now()->addDays(1)]);

            BoardCard::create(['column_id' => $inProgress->id, 'created_by' => $this->admin->id, 'title' => 'FitBody case study writing', 'description' => 'Draft ready, need to add metrics and before/after screenshots', 'position' => 0, 'color' => '#3B82F6', 'due_date' => now()->addDays(2)]);
            BoardCard::create(['column_id' => $inProgress->id, 'created_by' => $this->admin->id, 'title' => 'Design carousel for personal brand', 'description' => '5 Branding Mistakes - working on slide 7/10', 'position' => 1, 'due_date' => now()->addDays(1)]);

            BoardCard::create(['column_id' => $done->id, 'created_by' => $this->admin->id, 'title' => 'Client check-in calls', 'description' => 'Weekly calls with all active clients completed', 'position' => 0, 'color' => '#10B981']);
            BoardCard::create(['column_id' => $done->id, 'created_by' => $this->admin->id, 'title' => 'Analytics report - January', 'description' => 'Monthly performance report for all clients', 'position' => 1]);
            BoardCard::create(['column_id' => $done->id, 'created_by' => $this->admin->id, 'title' => 'Schedule next week posts', 'description' => 'All personal brand posts scheduled for next week', 'position' => 2]);
        }

        $this->command->info('Kanban boards created (3 boards).');
    }

    private function createCalendarEvents(): void
    {
        $events = [
            ['title' => 'Content Planning Session', 'description' => 'Weekly content planning for all clients', 'color' => '#3B82F6', 'event_type' => CalendarEventType::Meeting, 'starts_at' => now()->next('Monday')->setTime(9, 0), 'ends_at' => now()->next('Monday')->setTime(10, 30), 'all_day' => false],
            ['title' => 'Client Call - Maria (Life Coaching)', 'description' => 'Weekly check-in. Discuss YouTube expansion strategy.', 'color' => '#10B981', 'event_type' => CalendarEventType::Meeting, 'starts_at' => now()->next('Monday')->setTime(11, 0), 'ends_at' => now()->next('Monday')->setTime(11, 45), 'all_day' => false],
            ['title' => 'Client Call - Tomasz (FitBody)', 'description' => 'Review last week performance. Plan new Reels.', 'color' => '#10B981', 'event_type' => CalendarEventType::Meeting, 'starts_at' => now()->next('Tuesday')->setTime(10, 0), 'ends_at' => now()->next('Tuesday')->setTime(10, 30), 'all_day' => false],
            ['title' => 'Reel Recording Day', 'description' => 'Batch record Reels for personal brand and clients', 'color' => '#8B5CF6', 'event_type' => CalendarEventType::Reminder, 'starts_at' => now()->next('Wednesday')->setTime(13, 0), 'ends_at' => now()->next('Wednesday')->setTime(17, 0), 'all_day' => false],
            ['title' => 'Client Call - Agnieszka (Legal)', 'description' => 'Onboarding follow-up. Review first content drafts.', 'color' => '#10B981', 'event_type' => CalendarEventType::Meeting, 'starts_at' => now()->next('Thursday')->setTime(14, 0), 'ends_at' => now()->next('Thursday')->setTime(14, 30), 'all_day' => false],
            ['title' => 'Analytics Review', 'description' => 'Weekly performance review across all platforms', 'color' => '#F59E0B', 'event_type' => CalendarEventType::Reminder, 'starts_at' => now()->next('Friday')->setTime(9, 0), 'ends_at' => now()->next('Friday')->setTime(10, 0), 'all_day' => false],
            ['title' => 'Invoice Day', 'description' => 'Send monthly invoices to all active clients', 'color' => '#EF4444', 'event_type' => CalendarEventType::Reminder, 'starts_at' => now()->startOfMonth()->addMonth(), 'ends_at' => now()->startOfMonth()->addMonth(), 'all_day' => true],
            ['title' => 'Discovery Call - Marek (Smart Invest)', 'description' => 'Potential client. Interested in LinkedIn strategy.', 'color' => '#F59E0B', 'event_type' => CalendarEventType::Meeting, 'starts_at' => now()->addDays(3)->setTime(15, 0), 'ends_at' => now()->addDays(3)->setTime(15, 45), 'all_day' => false],
        ];

        foreach ($events as $eventData) {
            CalendarEvent::firstOrCreate(
                ['user_id' => $this->admin->id, 'title' => $eventData['title']],
                $eventData
            );
        }

        $this->command->info('Calendar events created (' . count($events) . ' events).');
    }

    private function createNotifications(): void
    {
        $notifications = [
            ['type' => 'post_published', 'title' => 'Post published', 'message' => '"5 Mistakes in Personal Branding" has been published successfully across all platforms.', 'data' => ['post_title' => '5 Mistakes in Personal Branding'], 'read_at' => now()->subDays(5)],
            ['type' => 'post_published', 'title' => 'Post published', 'message' => '"How I Grew to 10k Followers" has been published successfully across all platforms.', 'data' => ['post_title' => 'How I Grew to 10k Followers'], 'read_at' => now()->subDays(3)],
            ['type' => 'engagement_milestone', 'title' => 'Engagement milestone', 'message' => 'Your post "5 Mistakes in Personal Branding" reached 100 comments!', 'data' => ['milestone' => '100_comments'], 'read_at' => now()->subDays(2)],
            ['type' => 'new_lead', 'title' => 'New lead', 'message' => 'New inquiry from Ola Kaminska (Design Studio OK) via website contact form.', 'data' => ['lead_name' => 'Ola Kaminska'], 'read_at' => null],
            ['type' => 'approval_needed', 'title' => 'Approval needed', 'message' => '"Monday Motivation" post is waiting for your approval.', 'data' => ['post_title' => 'Monday Motivation'], 'read_at' => null],
            ['type' => 'post_scheduled', 'title' => 'Post scheduled', 'message' => '"Content Calendar Template" has been scheduled for ' . now()->addDays(2)->format('M d, Y') . '.', 'data' => ['post_title' => 'Content Calendar Template'], 'read_at' => null],
        ];

        foreach ($notifications as $notifData) {
            Notification::firstOrCreate(
                ['user_id' => $this->admin->id, 'title' => $notifData['title'], 'message' => $notifData['message']],
                array_merge($notifData, ['user_id' => $this->admin->id])
            );
        }

        $this->command->info('Notifications created (' . count($notifications) . ' notifications).');
    }

    /**
     * Helper to set cell values for a row.
     */
    private function setCellValues($row, array $fields, array $data): void
    {
        foreach ($data as $key => $value) {
            if (!isset($fields[$key])) {
                continue;
            }

            $field = $fields[$key];

            // Skip empty values
            if ($value === '' || $value === null) {
                continue;
            }

            // Handle select fields - find choice ID by name
            if ($field->type === FieldType::SELECT && is_string($value)) {
                $choices = $field->options['choices'] ?? [];
                foreach ($choices as $choice) {
                    if ($choice['name'] === $value) {
                        $value = $choice['id'];
                        break;
                    }
                }
            }

            // Handle multi_select fields - find choice IDs by names
            if ($field->type === FieldType::MULTI_SELECT && is_array($value)) {
                $choices = $field->options['choices'] ?? [];
                $ids = [];
                foreach ($value as $name) {
                    foreach ($choices as $choice) {
                        if ($choice['name'] === $name) {
                            $ids[] = $choice['id'];
                            break;
                        }
                    }
                }
                $value = $ids;
            }

            $row->setCellValue($field->id, $value);
        }
    }
}
