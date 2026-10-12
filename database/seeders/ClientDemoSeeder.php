<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\Partner;
use App\Models\SavedJob;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClientDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // 1. Super Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@demo.vcreateventures.com'],
            [
                'name' => 'Platform Super Admin',
                'password' => Hash::make('password'),
                'phone' => '+91 99999 00001',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['super_admin']);

        // 2. Primary Partner: TechCorp Solutions
        $techcorp = Partner::updateOrCreate(
            ['slug' => 'techcorp-solutions'],
            [
                'name' => 'TechCorp Solutions Pvt Ltd',
                'display_name' => 'TechCorp Solutions',
                'tagline' => 'Empowering Enterprise Digital & Cloud Transformation',
                'industry' => 'Software & Technology',
                'company_size' => '201-500',
                'website' => 'https://techcorp.vcreateventures.com',
                'email' => 'careers@techcorp.vcreateventures.com',
                'phone' => '+91 80 4123 4567',
                'location' => 'Bengaluru, Karnataka, India',
                'address' => 'Level 4, Cyber Park, Electronic City Phase 1, Bengaluru 560100',
                'about' => 'TechCorp is a premier enterprise digital solutions provider developing cloud software, distributed microservices, and AI-driven automation workflows.',
                'founded_year' => 2018,
                'status' => 'active',
                'max_active_jobs' => 25,
                'created_by' => $admin->id,
                'benefits' => [
                    'Competitive Compensation & Performance ESOPs',
                    'Comprehensive Health & Family Insurance (₹10L Cover)',
                    'Flexible Hybrid & Work-From-Home Policies',
                    'Annual Tech & Upskilling Certification Stipend',
                    'Generous Paid Time Off & Wellness Days',
                ],
                'social_links' => [
                    'linkedin' => 'https://linkedin.com/company/techcorp-sample',
                    'twitter' => 'https://twitter.com/techcorp_sample',
                ],
            ]
        );

        // Additional Indian Partners
        $tata = Partner::updateOrCreate(
            ['slug' => 'tata-digital'],
            [
                'name' => 'Tata Digital',
                'display_name' => 'Tata Digital',
                'tagline' => 'Building India’s Largest Omnichannel Super App',
                'industry' => 'E-Commerce & Digital Services',
                'company_size' => '1000+',
                'website' => 'https://tatadigital.sample.com',
                'email' => 'hiring@tatadigital.sample.com',
                'location' => 'Mumbai, Maharashtra, India',
                'about' => 'Tata Digital is at the forefront of powering digital India through seamless consumer commerce, fintech, and digital services.',
                'status' => 'active',
                'max_active_jobs' => 50,
                'created_by' => $admin->id,
            ]
        );

        $wipro = Partner::updateOrCreate(
            ['slug' => 'wipro-technologies'],
            [
                'name' => 'Wipro Technologies',
                'display_name' => 'Wipro Technologies',
                'tagline' => 'Reinventing Enterprise IT & Digital Cloud',
                'industry' => 'IT Services & Consulting',
                'company_size' => '1000+',
                'website' => 'https://wipro.sample.com',
                'email' => 'careers@wipro.sample.com',
                'location' => 'Noida, Uttar Pradesh, India',
                'about' => 'A global information technology, consulting, and business process services company.',
                'status' => 'active',
                'max_active_jobs' => 30,
                'created_by' => $admin->id,
            ]
        );

        $infosys = Partner::updateOrCreate(
            ['slug' => 'infosys-limited'],
            [
                'name' => 'Infosys Limited',
                'display_name' => 'Infosys Limited',
                'tagline' => 'Navigate Your Next with Digital Cloud & AI',
                'industry' => 'Information Technology',
                'company_size' => '1000+',
                'website' => 'https://infosys.sample.com',
                'location' => 'Bengaluru, Karnataka, India',
                'about' => 'A global leader in next-generation digital services and consulting.',
                'status' => 'active',
                'max_active_jobs' => 40,
                'created_by' => $admin->id,
            ]
        );

        // 3. Partner Users
        $partnerAdmin = User::updateOrCreate(
            ['email' => 'partner@demo.vcreateventures.com'],
            [
                'name' => 'Vikas Pandey',
                'password' => Hash::make('password'),
                'phone' => '+91 98765 43210',
                'partner_id' => $techcorp->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $partnerAdmin->syncRoles(['partner_admin']);

        $partnerRecruiter = User::updateOrCreate(
            ['email' => 'recruiter@demo.vcreateventures.com'],
            [
                'name' => 'Priya Sharma',
                'password' => Hash::make('password'),
                'phone' => '+91 91234 56789',
                'partner_id' => $techcorp->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $partnerRecruiter->syncRoles(['partner_recruiter']);

        // 4. Candidate User
        $candidate = User::updateOrCreate(
            ['email' => 'candidate@demo.vcreateventures.com'],
            [
                'name' => 'Aarav Sharma',
                'password' => Hash::make('password'),
                'phone' => '9876543210',
                'partner_id' => null,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $candidate->syncRoles(['candidate']);

        CandidateProfile::updateOrCreate(
            ['user_id' => $candidate->id],
            [
                'headline' => 'Senior Full Stack Engineer (React.js & Laravel)',
                'location' => 'Prayagraj, Uttar Pradesh, India',
                'bio' => 'Passionate Full Stack Engineer with 4+ years of hands-on experience building enterprise-grade web applications, REST APIs, and responsive frontends with React, Laravel, and PostgreSQL.',
                'skills' => [
                    'React.js',
                    'Laravel',
                    'JavaScript (ES6+)',
                    'TypeScript',
                    'Tailwind CSS',
                    'PHP 8',
                    'MySQL / SQLite',
                    'RESTful APIs',
                    'Git & GitHub',
                    'Docker',
                ],
                'experience' => [
                    [
                        'company' => 'Apex Cloud Labs',
                        'title' => 'Software Engineer',
                        'from' => '2023-01',
                        'to' => 'Present',
                        'current' => true,
                        'description' => 'Architected and built scalable SaaS web applications, optimized React rendering performance, and integrated secure Laravel Sanctum authentication.',
                    ],
                    [
                        'company' => 'Innovate Tech Solutions',
                        'title' => 'Associate Web Developer',
                        'from' => '2021-06',
                        'to' => '2022-12',
                        'current' => false,
                        'description' => 'Developed responsive frontend dashboards and created backend REST APIs for client projects.',
                    ],
                ],
                'education' => [
                    [
                        'school' => 'Motilal Nehru National Institute of Technology (MNNIT) Prayagraj',
                        'degree' => 'B.Tech',
                        'field' => 'Computer Science & Engineering',
                        'fromYear' => '2017',
                        'toYear' => '2021',
                    ],
                ],
            ]
        );

        // Additional dummy candidates for rich ATS Pipeline
        $c2 = User::updateOrCreate(
            ['email' => 'neha.gupta@example.com'],
            ['name' => 'Neha Gupta', 'password' => Hash::make('password'), 'status' => 'active', 'email_verified_at' => now()]
        );
        $c2->syncRoles(['candidate']);
        CandidateProfile::updateOrCreate(['user_id' => $c2->id], ['headline' => 'Product Designer & UI Specialist', 'location' => 'Bengaluru, Karnataka', 'skills' => ['Figma', 'UI/UX', 'Design Systems']]);

        $c3 = User::updateOrCreate(
            ['email' => 'siddharth.rao@example.com'],
            ['name' => 'Siddharth Rao', 'password' => Hash::make('password'), 'status' => 'active', 'email_verified_at' => now()]
        );
        $c3->syncRoles(['candidate']);
        CandidateProfile::updateOrCreate(['user_id' => $c3->id], ['headline' => 'Senior DevOps & Cloud Engineer', 'location' => 'Hyderabad, Telangana', 'skills' => ['Kubernetes', 'AWS', 'Terraform']]);

        $c4 = User::updateOrCreate(
            ['email' => 'kavita.nair@example.com'],
            ['name' => 'Kavita Nair', 'password' => Hash::make('password'), 'status' => 'active', 'email_verified_at' => now()]
        );
        $c4->syncRoles(['candidate']);
        CandidateProfile::updateOrCreate(['user_id' => $c4->id], ['headline' => 'Lead Frontend Engineer', 'location' => 'Noida, Uttar Pradesh', 'skills' => ['React', 'TypeScript', 'Next.js']]);

        // 5. Jobs Across Priority Uttar Pradesh and Metro Hubs
        $jobsData = [
            // UTTAR PRADESH - Priority Market
            [
                'partner_id' => $techcorp->id,
                'posted_by' => $partnerAdmin->id,
                'title' => 'Senior Full Stack Engineer (React + Laravel)',
                'slug' => 'senior-full-stack-engineer-react-laravel-prayagraj',
                'department' => 'Engineering',
                'location' => 'Prayagraj, Uttar Pradesh',
                'work_mode' => 'hybrid',
                'employment_type' => 'full_time',
                'min_exp' => 3,
                'max_exp' => 6,
                'salary_min' => 1200000,
                'salary_max' => 2000000,
                'currency' => 'INR',
                'openings' => 2,
                'status' => 'published',
                'published_at' => now()->subDays(3),
                'skills' => ['React', 'Laravel', 'PHP', 'Tailwind CSS', 'MySQL'],
                'description' => 'We are seeking an experienced Full Stack Engineer in Prayagraj to build cutting-edge web applications. You will take ownership of responsive user interfaces and robust Laravel backend APIs.',
                'requirements' => 'Bachelor degree in Computer Science or related field. 3+ years experience with React.js and Laravel framework.',
            ],
            [
                'partner_id' => $techcorp->id,
                'posted_by' => $partnerAdmin->id,
                'title' => 'Frontend React Developer',
                'slug' => 'frontend-react-developer-lucknow',
                'department' => 'Engineering',
                'location' => 'Lucknow, Uttar Pradesh',
                'work_mode' => 'onsite',
                'employment_type' => 'full_time',
                'min_exp' => 2,
                'max_exp' => 5,
                'salary_min' => 800000,
                'salary_max' => 1500000,
                'currency' => 'INR',
                'openings' => 3,
                'status' => 'published',
                'published_at' => now()->subDays(5),
                'skills' => ['React.js', 'TypeScript', 'Tailwind CSS', 'Redux / TanStack'],
                'description' => 'Join our dynamic frontend team in Lucknow. Build fast, responsive web applications with modern React tooling and beautiful UI systems.',
                'requirements' => '2+ years working with modern React and component libraries. Strong grasp of JavaScript fundamentals.',
            ],
            [
                'partner_id' => $wipro->id,
                'posted_by' => $admin->id,
                'title' => 'Senior DevOps & Cloud Architect',
                'slug' => 'senior-devops-cloud-architect-noida',
                'department' => 'Engineering',
                'location' => 'Noida, Uttar Pradesh',
                'work_mode' => 'hybrid',
                'employment_type' => 'full_time',
                'min_exp' => 5,
                'max_exp' => 10,
                'salary_min' => 2000000,
                'salary_max' => 3200000,
                'currency' => 'INR',
                'openings' => 1,
                'status' => 'published',
                'published_at' => now()->subDays(2),
                'skills' => ['AWS', 'Kubernetes', 'Docker', 'Terraform', 'CI/CD'],
                'description' => 'Lead the infrastructure automation and high-availability cloud deployments for our enterprise operations in Noida.',
                'requirements' => '5+ years experience designing scalable AWS cloud systems with Kubernetes and infrastructure as code.',
            ],
            [
                'partner_id' => $techcorp->id,
                'posted_by' => $partnerAdmin->id,
                'title' => 'Junior Software Trainee / Fresher',
                'slug' => 'junior-software-trainee-kanpur',
                'department' => 'Engineering',
                'location' => 'Kanpur, Uttar Pradesh',
                'work_mode' => 'onsite',
                'employment_type' => 'full_time',
                'min_exp' => 0,
                'max_exp' => 1,
                'salary_min' => 350000,
                'salary_max' => 550000,
                'currency' => 'INR',
                'openings' => 5,
                'status' => 'published',
                'published_at' => now()->subDays(1),
                'skills' => ['JavaScript', 'HTML/CSS', 'Python', 'SQL'],
                'description' => 'Exciting opportunity for fresh engineering graduates in Kanpur. Comprehensive onboarding and mentorship in modern web and cloud technologies.',
                'requirements' => 'Recent graduate in B.Tech / BCA / MCA. Passion for coding and solving real-world challenges.',
            ],
            [
                'partner_id' => $techcorp->id,
                'posted_by' => $partnerRecruiter->id,
                'title' => 'Backend Python Engineer',
                'slug' => 'backend-python-engineer-varanasi',
                'department' => 'Engineering',
                'location' => 'Varanasi, Uttar Pradesh',
                'work_mode' => 'remote',
                'employment_type' => 'full_time',
                'min_exp' => 2,
                'max_exp' => 4,
                'salary_min' => 700000,
                'salary_max' => 1200000,
                'currency' => 'INR',
                'openings' => 2,
                'status' => 'published',
                'published_at' => now()->subDays(4),
                'skills' => ['Python', 'FastAPI', 'PostgreSQL', 'Redis'],
                'description' => 'Build high-performance REST and GraphQL APIs using modern Python and async frameworks.',
                'requirements' => '2+ years writing clean, tested Python web services.',
            ],

            // OTHER METRO HUBS ACROSS INDIA
            [
                'partner_id' => $infosys->id,
                'posted_by' => $admin->id,
                'title' => 'Lead Cloud Infrastructure Engineer',
                'slug' => 'lead-cloud-infrastructure-engineer-bengaluru',
                'department' => 'Engineering',
                'location' => 'Bengaluru, Karnataka',
                'work_mode' => 'remote',
                'employment_type' => 'full_time',
                'min_exp' => 6,
                'max_exp' => 12,
                'salary_min' => 2500000,
                'salary_max' => 4200000,
                'currency' => 'INR',
                'openings' => 2,
                'status' => 'published',
                'published_at' => now()->subDays(6),
                'skills' => ['Cloud Architecture', 'AWS', 'GCP', 'Terraform', 'Microservices'],
                'description' => 'Drive large scale multi-cloud architectural strategies for enterprise clients across Asia and North America.',
                'requirements' => '6+ years in cloud architecture and distributed systems.',
            ],
            [
                'partner_id' => $tata->id,
                'posted_by' => $admin->id,
                'title' => 'Product Manager (Fintech & Payments)',
                'slug' => 'product-manager-fintech-payments-mumbai',
                'department' => 'Product & Design',
                'location' => 'Mumbai, Maharashtra',
                'work_mode' => 'hybrid',
                'employment_type' => 'full_time',
                'min_exp' => 4,
                'max_exp' => 8,
                'salary_min' => 1800000,
                'salary_max' => 3000000,
                'currency' => 'INR',
                'openings' => 1,
                'status' => 'published',
                'published_at' => now()->subDays(7),
                'skills' => ['Product Strategy', 'Fintech', 'UPI', 'Data Analytics'],
                'description' => 'Shape user payment journeys and checkout conversion funnels on India’s fastest growing omnichannel ecosystem.',
                'requirements' => '4+ years of product management experience within high-growth fintech or payments.',
            ],
            [
                'partner_id' => $techcorp->id,
                'posted_by' => $partnerAdmin->id,
                'title' => 'Lead UI/UX Product Designer',
                'slug' => 'lead-ui-ux-product-designer-pune',
                'department' => 'Product & Design',
                'location' => 'Pune, Maharashtra',
                'work_mode' => 'hybrid',
                'employment_type' => 'full_time',
                'min_exp' => 3,
                'max_exp' => 7,
                'salary_min' => 1100000,
                'salary_max' => 1900000,
                'currency' => 'INR',
                'openings' => 2,
                'status' => 'published',
                'published_at' => now()->subDays(8),
                'skills' => ['Figma', 'User Research', 'Design Systems', 'Prototyping'],
                'description' => 'Craft intuitive user interfaces and define design tokens and reusable components.',
                'requirements' => '3+ years product design experience with an impressive portfolio of SaaS tools.',
            ],

            // MODERATION QUEUE (pending_review)
            [
                'partner_id' => $techcorp->id,
                'posted_by' => $partnerAdmin->id,
                'title' => 'AI Research Scientist (LLMs & Vision)',
                'slug' => 'ai-research-scientist-llms-bengaluru',
                'department' => 'Engineering',
                'location' => 'Bengaluru, Karnataka',
                'work_mode' => 'remote',
                'employment_type' => 'full_time',
                'min_exp' => 4,
                'max_exp' => 8,
                'salary_min' => 2800000,
                'salary_max' => 4500000,
                'currency' => 'INR',
                'openings' => 1,
                'status' => 'pending_review',
                'published_at' => null,
                'skills' => ['PyTorch', 'Transformers', 'Fine-Tuning', 'LangChain'],
                'description' => 'Research and fine-tune large multimodal language models for enterprise workflows.',
                'requirements' => 'Advanced degree in CS/AI or equivalent research publications.',
            ],
            [
                'partner_id' => $techcorp->id,
                'posted_by' => $partnerRecruiter->id,
                'title' => 'Mobile App Developer (React Native)',
                'slug' => 'mobile-app-developer-react-native-lucknow',
                'department' => 'Engineering',
                'location' => 'Lucknow, Uttar Pradesh',
                'work_mode' => 'hybrid',
                'employment_type' => 'full_time',
                'min_exp' => 2,
                'max_exp' => 5,
                'salary_min' => 900000,
                'salary_max' => 1600000,
                'currency' => 'INR',
                'openings' => 2,
                'status' => 'pending_review',
                'published_at' => null,
                'skills' => ['React Native', 'iOS', 'Android', 'TypeScript'],
                'description' => 'Develop high-performance cross-platform mobile apps for candidate job searching.',
                'requirements' => '2+ years published React Native apps on App Store and Play Store.',
            ],

            // REJECTED (with moderation feedback)
            [
                'partner_id' => $techcorp->id,
                'posted_by' => $partnerRecruiter->id,
                'title' => 'Junior Sales Associate',
                'slug' => 'junior-sales-associate-noida',
                'department' => 'Sales & Marketing',
                'location' => 'Noida, Uttar Pradesh',
                'work_mode' => 'onsite',
                'employment_type' => 'full_time',
                'min_exp' => 1,
                'max_exp' => 3,
                'salary_min' => 400000,
                'salary_max' => 700000,
                'currency' => 'INR',
                'openings' => 2,
                'status' => 'rejected',
                'rejection_note' => 'Please provide clear workplace attendance requirements (hybrid/onsite days) and fixed vs variable commission details.',
                'published_at' => null,
                'skills' => ['B2B Sales', 'Lead Generation', 'Cold Calling'],
                'description' => 'Outreach to enterprise employers and manage sales pipelines.',
                'requirements' => 'Strong communication skills and willingness to travel for client meetings.',
            ],

            // DRAFT
            [
                'partner_id' => $techcorp->id,
                'posted_by' => $partnerAdmin->id,
                'title' => 'Technical Content Specialist',
                'slug' => 'technical-content-specialist-prayagraj',
                'department' => 'Sales & Marketing',
                'location' => 'Prayagraj, Uttar Pradesh',
                'work_mode' => 'remote',
                'employment_type' => 'contract',
                'min_exp' => 1,
                'max_exp' => 4,
                'salary_min' => 500000,
                'salary_max' => 800000,
                'currency' => 'INR',
                'openings' => 1,
                'status' => 'draft',
                'published_at' => null,
                'skills' => ['Technical Writing', 'SEO', 'Developer Docs'],
                'description' => 'Draft high-impact technical blog posts, SEO guides, and documentation for software developers.',
                'requirements' => 'Strong background in writing software tutorials and documentation.',
            ],

            // CLOSED
            [
                'partner_id' => $techcorp->id,
                'posted_by' => $partnerAdmin->id,
                'title' => 'Human Resources Operations Lead',
                'slug' => 'human-resources-operations-lead-bengaluru',
                'department' => 'Operations & HR',
                'location' => 'Bengaluru, Karnataka',
                'work_mode' => 'onsite',
                'employment_type' => 'full_time',
                'min_exp' => 5,
                'max_exp' => 8,
                'salary_min' => 1400000,
                'salary_max' => 2200000,
                'currency' => 'INR',
                'openings' => 1,
                'status' => 'closed',
                'published_at' => now()->subDays(30),
                'skills' => ['HR Management', 'Payroll', 'Compliance', 'Talent Acquisition'],
                'description' => 'Position closed as opening has been successfully filled.',
                'requirements' => '5+ years in HR management.',
            ],
        ];

        $createdJobs = [];
        foreach ($jobsData as $j) {
            $createdJobs[$j['slug']] = Job::updateOrCreate(['slug' => $j['slug']], $j);
        }

        // 6. ATS Applications & Stages
        $p1Job = $createdJobs['senior-full-stack-engineer-react-laravel-prayagraj'] ?? null;
        $p2Job = $createdJobs['frontend-react-developer-lucknow'] ?? null;
        $p3Job = $createdJobs['lead-cloud-infrastructure-engineer-bengaluru'] ?? null;

        if ($p1Job) {
            // Aarav Sharma in INTERVIEW stage
            Application::updateOrCreate(
                ['user_id' => $candidate->id, 'job_id' => $p1Job->id],
                [
                    'partner_id' => $p1Job->partner_id,
                    'status' => 'interview',
                    'cover_letter' => 'I have 4+ years building React & Laravel platforms and would love to contribute to TechCorp in Prayagraj.',
                    'status_note' => 'Technical interview scheduled with Lead Architect for Thursday at 3:00 PM.',
                    'status_history' => [
                        ['status' => 'applied', 'note' => 'Candidate submitted application', 'timestamp' => now()->subDays(3)->toISOString(), 'actor_id' => $candidate->id],
                        ['status' => 'shortlisted', 'note' => 'Resume matches engineering stack', 'timestamp' => now()->subDays(2)->toISOString(), 'actor_id' => $partnerAdmin->id],
                        ['status' => 'interview', 'note' => 'Technical round scheduled', 'timestamp' => now()->subDay()->toISOString(), 'actor_id' => $partnerAdmin->id],
                    ],
                ]
            );

            // Neha Gupta in SHORTLISTED
            Application::updateOrCreate(
                ['user_id' => $c2->id, 'job_id' => $p1Job->id],
                [
                    'partner_id' => $p1Job->partner_id,
                    'status' => 'shortlisted',
                    'cover_letter' => 'Experienced designer and UI developer interested in joining.',
                    'status_history' => [
                        ['status' => 'applied', 'timestamp' => now()->subDays(2)->toISOString(), 'actor_id' => $c2->id],
                        ['status' => 'shortlisted', 'timestamp' => now()->subDay()->toISOString(), 'actor_id' => $partnerAdmin->id],
                    ],
                ]
            );

            // Siddharth Rao in OFFERED
            Application::updateOrCreate(
                ['user_id' => $c3->id, 'job_id' => $p1Job->id],
                [
                    'partner_id' => $p1Job->partner_id,
                    'status' => 'offered',
                    'cover_letter' => 'Cloud & Fullstack specialist ready for immediate joining.',
                    'status_history' => [
                        ['status' => 'applied', 'timestamp' => now()->subDays(5)->toISOString(), 'actor_id' => $c3->id],
                        ['status' => 'shortlisted', 'timestamp' => now()->subDays(4)->toISOString(), 'actor_id' => $partnerAdmin->id],
                        ['status' => 'interview', 'timestamp' => now()->subDays(2)->toISOString(), 'actor_id' => $partnerAdmin->id],
                        ['status' => 'offered', 'note' => 'Official offer letter dispatched via email', 'timestamp' => now()->subHours(6)->toISOString(), 'actor_id' => $partnerAdmin->id],
                    ],
                ]
            );

            // Kavita Nair in APPLIED
            Application::updateOrCreate(
                ['user_id' => $c4->id, 'job_id' => $p1Job->id],
                [
                    'partner_id' => $p1Job->partner_id,
                    'status' => 'applied',
                    'cover_letter' => 'Senior frontend dev looking for high-scale challenges.',
                    'status_history' => [
                        ['status' => 'applied', 'timestamp' => now()->subHours(12)->toISOString(), 'actor_id' => $c4->id],
                    ],
                ]
            );
        }

        if ($p2Job) {
            // Aarav Sharma in SHORTLISTED for Lucknow job
            Application::updateOrCreate(
                ['user_id' => $candidate->id, 'job_id' => $p2Job->id],
                [
                    'partner_id' => $p2Job->partner_id,
                    'status' => 'shortlisted',
                    'cover_letter' => 'Excited to apply for Lucknow frontend position.',
                    'status_history' => [
                        ['status' => 'applied', 'timestamp' => now()->subDays(4)->toISOString(), 'actor_id' => $candidate->id],
                        ['status' => 'shortlisted', 'note' => 'Screened and shortlisted for tech assessment', 'timestamp' => now()->subDays(2)->toISOString(), 'actor_id' => $partnerRecruiter->id],
                    ],
                ]
            );
        }

        if ($p3Job) {
            // Aarav Sharma in APPLIED for Bengaluru Cloud Lead
            Application::updateOrCreate(
                ['user_id' => $candidate->id, 'job_id' => $p3Job->id],
                [
                    'partner_id' => $p3Job->partner_id,
                    'status' => 'applied',
                    'cover_letter' => 'Applying for remote cloud lead position.',
                    'status_history' => [
                        ['status' => 'applied', 'timestamp' => now()->subDays(1)->toISOString(), 'actor_id' => $candidate->id],
                    ],
                ]
            );
        }

        // 7. Candidate Saved Jobs
        if ($p1Job) {
            SavedJob::updateOrCreate(['user_id' => $candidate->id, 'job_id' => $p1Job->id]);
        }
        if ($p2Job) {
            SavedJob::updateOrCreate(['user_id' => $candidate->id, 'job_id' => $p2Job->id]);
        }

        // 8. Platform Audit Logs for Admin Dashboard feed
        AuditLog::create([
            'actor_id' => $admin->id,
            'partner_id' => $techcorp->id,
            'action' => 'job.moderated',
            'subject_type' => Job::class,
            'subject_id' => $p1Job?->id ?? 1,
            'meta' => ['status' => 'published', 'title' => 'Senior Full Stack Engineer (React + Laravel)'],
            'created_at' => now()->subDays(2),
        ]);

        AuditLog::create([
            'actor_id' => $partnerAdmin->id,
            'partner_id' => $techcorp->id,
            'action' => 'job.submitted_for_review',
            'subject_type' => Job::class,
            'subject_id' => $createdJobs['ai-research-scientist-llms-bengaluru']?->id ?? 2,
            'meta' => ['title' => 'AI Research Scientist (LLMs & Vision)'],
            'created_at' => now()->subHours(8),
        ]);

        AuditLog::create([
            'actor_id' => $admin->id,
            'partner_id' => $techcorp->id,
            'action' => 'partner.created',
            'subject_type' => Partner::class,
            'subject_id' => $techcorp->id,
            'meta' => ['name' => 'TechCorp Solutions Pvt Ltd'],
            'created_at' => now()->subDays(10),
        ]);

        AuditLog::create([
            'actor_id' => $admin->id,
            'partner_id' => $techcorp->id,
            'action' => 'job.rejected',
            'subject_type' => Job::class,
            'subject_id' => $createdJobs['junior-sales-associate-noida']?->id ?? 3,
            'meta' => ['title' => 'Junior Sales Associate', 'reason' => 'Workplace clarity required'],
            'created_at' => now()->subDays(1),
        ]);
    }
}

