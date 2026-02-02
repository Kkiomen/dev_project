<?php

namespace Database\Seeders;

use App\Enums\FieldType;
use App\Models\Base;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Create demo users with comprehensive database examples.
     *
     * Run with: php artisan db:seed --class=DemoDataSeeder
     */
    public function run(): void
    {
        $this->command->info('Creating demo users and databases...');

        // User 1: Sales Manager - CRM Database
        $user1 = $this->createUser(
            'Anna Kowalska',
            'anna@example.com',
        );
        $this->createCrmDatabase($user1);

        // User 2: Project Manager - Project Management Database
        $user2 = $this->createUser(
            'Jan Nowak',
            'jan@example.com',
        );
        $this->createProjectDatabase($user2);

        $this->command->info('Demo data created successfully!');
        $this->command->newLine();
        $this->command->info('Login credentials:');
        $this->command->info('  Email: anna@example.com | Password: password');
        $this->command->info('  Email: jan@example.com  | Password: password');
    }

    private function createUser(string $name, string $email): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }

    /**
     * Create CRM database with Contacts, Companies, and Deals tables.
     */
    private function createCrmDatabase(User $user): void
    {
        $this->command->info("Creating CRM database for {$user->name}...");

        $base = Base::create([
            'user_id' => $user->id,
            'name' => 'CRM - Sprzedaż',
            'description' => 'Zarządzanie kontaktami, firmami i szansami sprzedaży',
            'color' => '#3B82F6',
            'icon' => '💼',
        ]);

        // ========== CONTACTS TABLE ==========
        $contactsTable = $base->tables()->create([
            'name' => 'Kontakty',
            'description' => 'Lista wszystkich kontaktów biznesowych',
        ]);

        // Fields for Contacts
        $contactFields = [];
        $contactFields['name'] = $contactsTable->fields()->create([
            'name' => 'Imię i nazwisko',
            'type' => FieldType::TEXT,
            'is_primary' => true,
        ]);

        $contactFields['email'] = $contactsTable->fields()->create([
            'name' => 'Email',
            'type' => FieldType::TEXT,
        ]);

        $contactFields['phone'] = $contactsTable->fields()->create([
            'name' => 'Telefon',
            'type' => FieldType::TEXT,
        ]);

        $contactFields['company'] = $contactsTable->fields()->create([
            'name' => 'Firma',
            'type' => FieldType::TEXT,
        ]);

        $contactFields['position'] = $contactsTable->fields()->create([
            'name' => 'Stanowisko',
            'type' => FieldType::TEXT,
        ]);

        $contactFields['status'] = $contactsTable->fields()->create([
            'name' => 'Status',
            'type' => FieldType::SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Nowy', 'color' => '#3B82F6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'W kontakcie', 'color' => '#F59E0B'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Kwalifikowany', 'color' => '#10B981'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Nieaktywny', 'color' => '#6B7280'],
                ],
            ],
        ]);

        $contactFields['source'] = $contactsTable->fields()->create([
            'name' => 'Źródło',
            'type' => FieldType::SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Strona WWW', 'color' => '#8B5CF6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'LinkedIn', 'color' => '#0077B5'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Polecenie', 'color' => '#10B981'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Targi', 'color' => '#F59E0B'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Cold call', 'color' => '#EF4444'],
                ],
            ],
        ]);

        $contactFields['tags'] = $contactsTable->fields()->create([
            'name' => 'Tagi',
            'type' => FieldType::MULTI_SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'VIP', 'color' => '#EF4444'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Decydent', 'color' => '#8B5CF6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Techniczny', 'color' => '#3B82F6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Finanse', 'color' => '#10B981'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Marketing', 'color' => '#F59E0B'],
                ],
            ],
        ]);

        $contactFields['linkedin'] = $contactsTable->fields()->create([
            'name' => 'LinkedIn',
            'type' => FieldType::URL,
        ]);

        $contactFields['notes'] = $contactsTable->fields()->create([
            'name' => 'Notatki',
            'type' => FieldType::TEXT,
        ]);

        $contactFields['last_contact'] = $contactsTable->fields()->create([
            'name' => 'Ostatni kontakt',
            'type' => FieldType::DATE,
        ]);

        $contactFields['newsletter'] = $contactsTable->fields()->create([
            'name' => 'Newsletter',
            'type' => FieldType::CHECKBOX,
        ]);

        $contactFields['value'] = $contactsTable->fields()->create([
            'name' => 'Wartość (PLN)',
            'type' => FieldType::NUMBER,
        ]);

        // Sample contacts data
        $contacts = [
            [
                'name' => 'Piotr Wiśniewski',
                'email' => 'p.wisniewski@techcorp.pl',
                'phone' => '+48 601 234 567',
                'company' => 'TechCorp Sp. z o.o.',
                'position' => 'Dyrektor IT',
                'status' => 'Kwalifikowany',
                'source' => 'LinkedIn',
                'tags' => ['VIP', 'Decydent', 'Techniczny'],
                'linkedin' => 'https://linkedin.com/in/piotrwisniewski',
                'notes' => 'Zainteresowany wdrożeniem systemu CRM. Spotkanie zaplanowane na przyszły tydzień.',
                'last_contact' => '2026-01-28',
                'newsletter' => true,
                'value' => 150000,
            ],
            [
                'name' => 'Magdalena Zielińska',
                'email' => 'm.zielinska@innovate.com',
                'phone' => '+48 502 345 678',
                'company' => 'Innovate Solutions',
                'position' => 'CEO',
                'status' => 'W kontakcie',
                'source' => 'Targi',
                'tags' => ['VIP', 'Decydent'],
                'linkedin' => 'https://linkedin.com/in/magdalenazielinska',
                'notes' => 'Poznana na targach IT. Prowadzi firmę konsultingową.',
                'last_contact' => '2026-01-25',
                'newsletter' => true,
                'value' => 80000,
            ],
            [
                'name' => 'Tomasz Kowalczyk',
                'email' => 't.kowalczyk@startup.io',
                'phone' => '+48 603 456 789',
                'company' => 'StartupIO',
                'position' => 'CTO',
                'status' => 'Nowy',
                'source' => 'Strona WWW',
                'tags' => ['Techniczny'],
                'linkedin' => 'https://linkedin.com/in/tomaszkowalczyk',
                'notes' => 'Wypełnił formularz kontaktowy na stronie.',
                'last_contact' => '2026-01-30',
                'newsletter' => false,
                'value' => 25000,
            ],
            [
                'name' => 'Karolina Dąbrowska',
                'email' => 'k.dabrowska@mediahouse.pl',
                'phone' => '+48 504 567 890',
                'company' => 'MediaHouse',
                'position' => 'Marketing Director',
                'status' => 'Kwalifikowany',
                'source' => 'Polecenie',
                'tags' => ['Marketing', 'Decydent'],
                'linkedin' => 'https://linkedin.com/in/karolinadabrowska',
                'notes' => 'Polecona przez Piotra Wiśniewskiego. Szuka rozwiązania do automatyzacji marketingu.',
                'last_contact' => '2026-01-29',
                'newsletter' => true,
                'value' => 65000,
            ],
            [
                'name' => 'Andrzej Lewandowski',
                'email' => 'a.lewandowski@fingroup.pl',
                'phone' => '+48 605 678 901',
                'company' => 'FinGroup SA',
                'position' => 'CFO',
                'status' => 'W kontakcie',
                'source' => 'Cold call',
                'tags' => ['Finanse', 'Decydent'],
                'linkedin' => 'https://linkedin.com/in/andrzejlewandowski',
                'notes' => 'Zainteresowany integracją z systemem księgowym.',
                'last_contact' => '2026-01-27',
                'newsletter' => true,
                'value' => 200000,
            ],
            [
                'name' => 'Natalia Wójcik',
                'email' => 'n.wojcik@ecommerce.pl',
                'phone' => '+48 506 789 012',
                'company' => 'E-Commerce Plus',
                'position' => 'Head of Operations',
                'status' => 'Nowy',
                'source' => 'LinkedIn',
                'tags' => ['Techniczny'],
                'linkedin' => 'https://linkedin.com/in/nataliawojcik',
                'notes' => 'Odpowiedziała na wiadomość na LinkedIn.',
                'last_contact' => '2026-01-31',
                'newsletter' => false,
                'value' => 45000,
            ],
            [
                'name' => 'Michał Szymański',
                'email' => 'm.szymanski@logistic.com',
                'phone' => '+48 607 890 123',
                'company' => 'LogisticPro',
                'position' => 'Operations Manager',
                'status' => 'Nieaktywny',
                'source' => 'Targi',
                'tags' => [],
                'linkedin' => '',
                'notes' => 'Nie odpowiada na maile od 2 miesięcy.',
                'last_contact' => '2025-11-15',
                'newsletter' => false,
                'value' => 0,
            ],
            [
                'name' => 'Ewa Kamińska',
                'email' => 'e.kaminska@design.studio',
                'phone' => '+48 508 901 234',
                'company' => 'Design Studio',
                'position' => 'Creative Director',
                'status' => 'Kwalifikowany',
                'source' => 'Polecenie',
                'tags' => ['Marketing', 'VIP'],
                'linkedin' => 'https://linkedin.com/in/ewakaminska',
                'notes' => 'Prowadzi agencję kreatywną. Zainteresowana współpracą długoterminową.',
                'last_contact' => '2026-01-30',
                'newsletter' => true,
                'value' => 120000,
            ],
        ];

        foreach ($contacts as $contactData) {
            $row = $contactsTable->rows()->create();
            $this->setCellValues($row, $contactFields, $contactData);
        }

        // ========== COMPANIES TABLE ==========
        $companiesTable = $base->tables()->create([
            'name' => 'Firmy',
            'description' => 'Baza firm i organizacji',
        ]);

        $companyFields = [];
        $companyFields['name'] = $companiesTable->fields()->create([
            'name' => 'Nazwa firmy',
            'type' => FieldType::TEXT,
            'is_primary' => true,
        ]);

        $companyFields['industry'] = $companiesTable->fields()->create([
            'name' => 'Branża',
            'type' => FieldType::SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'IT / Software', 'color' => '#3B82F6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Finanse', 'color' => '#10B981'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Marketing / Media', 'color' => '#F59E0B'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'E-commerce', 'color' => '#8B5CF6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Logistyka', 'color' => '#6B7280'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Produkcja', 'color' => '#EF4444'],
                ],
            ],
        ]);

        $companyFields['size'] = $companiesTable->fields()->create([
            'name' => 'Wielkość',
            'type' => FieldType::SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Startup (1-10)', 'color' => '#10B981'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Mała (11-50)', 'color' => '#3B82F6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Średnia (51-200)', 'color' => '#F59E0B'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Duża (201-1000)', 'color' => '#8B5CF6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Enterprise (1000+)', 'color' => '#EF4444'],
                ],
            ],
        ]);

        $companyFields['website'] = $companiesTable->fields()->create([
            'name' => 'Strona WWW',
            'type' => FieldType::URL,
        ]);

        $companyFields['nip'] = $companiesTable->fields()->create([
            'name' => 'NIP',
            'type' => FieldType::TEXT,
        ]);

        $companyFields['address'] = $companiesTable->fields()->create([
            'name' => 'Adres',
            'type' => FieldType::TEXT,
        ]);

        $companyFields['revenue'] = $companiesTable->fields()->create([
            'name' => 'Przychód roczny (PLN)',
            'type' => FieldType::NUMBER,
        ]);

        $companyFields['active'] = $companiesTable->fields()->create([
            'name' => 'Aktywny klient',
            'type' => FieldType::CHECKBOX,
        ]);

        $companies = [
            ['name' => 'TechCorp Sp. z o.o.', 'industry' => 'IT / Software', 'size' => 'Średnia (51-200)', 'website' => 'https://techcorp.pl', 'nip' => '1234567890', 'address' => 'ul. Marszałkowska 100, Warszawa', 'revenue' => 15000000, 'active' => true],
            ['name' => 'Innovate Solutions', 'industry' => 'IT / Software', 'size' => 'Mała (11-50)', 'website' => 'https://innovate.com', 'nip' => '2345678901', 'address' => 'ul. Krakowska 50, Kraków', 'revenue' => 5000000, 'active' => true],
            ['name' => 'FinGroup SA', 'industry' => 'Finanse', 'size' => 'Duża (201-1000)', 'website' => 'https://fingroup.pl', 'nip' => '3456789012', 'address' => 'ul. Bankowa 1, Warszawa', 'revenue' => 50000000, 'active' => true],
            ['name' => 'MediaHouse', 'industry' => 'Marketing / Media', 'size' => 'Mała (11-50)', 'website' => 'https://mediahouse.pl', 'nip' => '4567890123', 'address' => 'ul. Medialna 25, Poznań', 'revenue' => 8000000, 'active' => true],
            ['name' => 'E-Commerce Plus', 'industry' => 'E-commerce', 'size' => 'Średnia (51-200)', 'website' => 'https://ecommerce.pl', 'nip' => '5678901234', 'address' => 'ul. Handlowa 10, Wrocław', 'revenue' => 25000000, 'active' => false],
        ];

        foreach ($companies as $companyData) {
            $row = $companiesTable->rows()->create();
            $this->setCellValues($row, $companyFields, $companyData);
        }

        // ========== DEALS TABLE ==========
        $dealsTable = $base->tables()->create([
            'name' => 'Szanse sprzedaży',
            'description' => 'Pipeline sprzedażowy',
        ]);

        $dealFields = [];
        $dealFields['name'] = $dealsTable->fields()->create([
            'name' => 'Nazwa',
            'type' => FieldType::TEXT,
            'is_primary' => true,
        ]);

        $dealFields['company'] = $dealsTable->fields()->create([
            'name' => 'Firma',
            'type' => FieldType::TEXT,
        ]);

        $dealFields['value'] = $dealsTable->fields()->create([
            'name' => 'Wartość (PLN)',
            'type' => FieldType::NUMBER,
        ]);

        $dealFields['stage'] = $dealsTable->fields()->create([
            'name' => 'Etap',
            'type' => FieldType::SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Prospekt', 'color' => '#6B7280'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Kwalifikacja', 'color' => '#3B82F6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Propozycja', 'color' => '#F59E0B'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Negocjacje', 'color' => '#8B5CF6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Wygrana', 'color' => '#10B981'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Przegrana', 'color' => '#EF4444'],
                ],
            ],
        ]);

        $dealFields['probability'] = $dealsTable->fields()->create([
            'name' => 'Prawdopodobieństwo (%)',
            'type' => FieldType::NUMBER,
        ]);

        $dealFields['close_date'] = $dealsTable->fields()->create([
            'name' => 'Planowane zamknięcie',
            'type' => FieldType::DATE,
        ]);

        $deals = [
            ['name' => 'Wdrożenie CRM dla TechCorp', 'company' => 'TechCorp Sp. z o.o.', 'value' => 150000, 'stage' => 'Negocjacje', 'probability' => 75, 'close_date' => '2026-02-28'],
            ['name' => 'Automatyzacja marketingu', 'company' => 'MediaHouse', 'value' => 65000, 'stage' => 'Propozycja', 'probability' => 50, 'close_date' => '2026-03-15'],
            ['name' => 'Integracja systemów FinGroup', 'company' => 'FinGroup SA', 'value' => 200000, 'stage' => 'Kwalifikacja', 'probability' => 30, 'close_date' => '2026-04-30'],
            ['name' => 'Konsulting Innovate', 'company' => 'Innovate Solutions', 'value' => 80000, 'stage' => 'Wygrana', 'probability' => 100, 'close_date' => '2026-01-15'],
            ['name' => 'E-commerce integration', 'company' => 'E-Commerce Plus', 'value' => 45000, 'stage' => 'Prospekt', 'probability' => 10, 'close_date' => '2026-05-30'],
        ];

        foreach ($deals as $dealData) {
            $row = $dealsTable->rows()->create();
            $this->setCellValues($row, $dealFields, $dealData);
        }
    }

    /**
     * Create Project Management database with Projects, Tasks, and Team tables.
     */
    private function createProjectDatabase(User $user): void
    {
        $this->command->info("Creating Project Management database for {$user->name}...");

        $base = Base::create([
            'user_id' => $user->id,
            'name' => 'Zarządzanie Projektami',
            'description' => 'Projekty, zadania i zespół',
            'color' => '#8B5CF6',
            'icon' => '📋',
        ]);

        // ========== PROJECTS TABLE ==========
        $projectsTable = $base->tables()->create([
            'name' => 'Projekty',
            'description' => 'Lista wszystkich projektów',
        ]);

        $projectFields = [];
        $projectFields['name'] = $projectsTable->fields()->create([
            'name' => 'Nazwa projektu',
            'type' => FieldType::TEXT,
            'is_primary' => true,
        ]);

        $projectFields['client'] = $projectsTable->fields()->create([
            'name' => 'Klient',
            'type' => FieldType::TEXT,
        ]);

        $projectFields['status'] = $projectsTable->fields()->create([
            'name' => 'Status',
            'type' => FieldType::SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Planowany', 'color' => '#6B7280'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'W trakcie', 'color' => '#3B82F6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Wstrzymany', 'color' => '#F59E0B'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Zakończony', 'color' => '#10B981'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Anulowany', 'color' => '#EF4444'],
                ],
            ],
        ]);

        $projectFields['priority'] = $projectsTable->fields()->create([
            'name' => 'Priorytet',
            'type' => FieldType::SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Niski', 'color' => '#10B981'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Średni', 'color' => '#F59E0B'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Wysoki', 'color' => '#EF4444'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Krytyczny', 'color' => '#7C3AED'],
                ],
            ],
        ]);

        $projectFields['start_date'] = $projectsTable->fields()->create([
            'name' => 'Data rozpoczęcia',
            'type' => FieldType::DATE,
        ]);

        $projectFields['end_date'] = $projectsTable->fields()->create([
            'name' => 'Termin',
            'type' => FieldType::DATE,
        ]);

        $projectFields['budget'] = $projectsTable->fields()->create([
            'name' => 'Budżet (PLN)',
            'type' => FieldType::NUMBER,
        ]);

        $projectFields['progress'] = $projectsTable->fields()->create([
            'name' => 'Postęp (%)',
            'type' => FieldType::NUMBER,
        ]);

        $projectFields['description'] = $projectsTable->fields()->create([
            'name' => 'Opis',
            'type' => FieldType::TEXT,
        ]);

        $projectFields['tags'] = $projectsTable->fields()->create([
            'name' => 'Technologie',
            'type' => FieldType::MULTI_SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Laravel', 'color' => '#EF4444'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Vue.js', 'color' => '#10B981'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'React', 'color' => '#3B82F6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Node.js', 'color' => '#22C55E'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Python', 'color' => '#F59E0B'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Docker', 'color' => '#0EA5E9'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'AWS', 'color' => '#F97316'],
                ],
            ],
        ]);

        $projects = [
            ['name' => 'Portal klienta TechCorp', 'client' => 'TechCorp', 'status' => 'W trakcie', 'priority' => 'Wysoki', 'start_date' => '2026-01-02', 'end_date' => '2026-03-31', 'budget' => 180000, 'progress' => 45, 'description' => 'Portal samoobsługowy dla klientów z integracją CRM', 'tags' => ['Laravel', 'Vue.js', 'Docker']],
            ['name' => 'Aplikacja mobilna FinGroup', 'client' => 'FinGroup SA', 'status' => 'Planowany', 'priority' => 'Krytyczny', 'start_date' => '2026-02-15', 'end_date' => '2026-06-30', 'budget' => 350000, 'progress' => 0, 'description' => 'Aplikacja mobilna do zarządzania finansami', 'tags' => ['React', 'Node.js', 'AWS']],
            ['name' => 'Redesign strony MediaHouse', 'client' => 'MediaHouse', 'status' => 'W trakcie', 'priority' => 'Średni', 'start_date' => '2026-01-10', 'end_date' => '2026-02-28', 'budget' => 45000, 'progress' => 70, 'description' => 'Nowy design i optymalizacja wydajności', 'tags' => ['Vue.js']],
            ['name' => 'System raportowania', 'client' => 'Wewnętrzny', 'status' => 'Zakończony', 'priority' => 'Niski', 'start_date' => '2025-11-01', 'end_date' => '2025-12-20', 'budget' => 25000, 'progress' => 100, 'description' => 'Automatyczne raporty i dashboardy', 'tags' => ['Laravel', 'Python']],
            ['name' => 'API integracyjne', 'client' => 'E-Commerce Plus', 'status' => 'Wstrzymany', 'priority' => 'Średni', 'start_date' => '2025-12-01', 'end_date' => '2026-02-28', 'budget' => 60000, 'progress' => 30, 'description' => 'Integracja z zewnętrznymi systemami', 'tags' => ['Laravel', 'Docker', 'AWS']],
        ];

        foreach ($projects as $projectData) {
            $row = $projectsTable->rows()->create();
            $this->setCellValues($row, $projectFields, $projectData);
        }

        // ========== TASKS TABLE ==========
        $tasksTable = $base->tables()->create([
            'name' => 'Zadania',
            'description' => 'Lista zadań do wykonania',
        ]);

        $taskFields = [];
        $taskFields['title'] = $tasksTable->fields()->create([
            'name' => 'Tytuł',
            'type' => FieldType::TEXT,
            'is_primary' => true,
        ]);

        $taskFields['project'] = $tasksTable->fields()->create([
            'name' => 'Projekt',
            'type' => FieldType::TEXT,
        ]);

        $taskFields['assignee'] = $tasksTable->fields()->create([
            'name' => 'Przypisany',
            'type' => FieldType::TEXT,
        ]);

        $taskFields['status'] = $tasksTable->fields()->create([
            'name' => 'Status',
            'type' => FieldType::SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Do zrobienia', 'color' => '#6B7280'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'W trakcie', 'color' => '#3B82F6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Review', 'color' => '#F59E0B'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Gotowe', 'color' => '#10B981'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Zablokowane', 'color' => '#EF4444'],
                ],
            ],
        ]);

        $taskFields['priority'] = $tasksTable->fields()->create([
            'name' => 'Priorytet',
            'type' => FieldType::SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Niski', 'color' => '#10B981'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Średni', 'color' => '#F59E0B'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Wysoki', 'color' => '#EF4444'],
                ],
            ],
        ]);

        $taskFields['due_date'] = $tasksTable->fields()->create([
            'name' => 'Termin',
            'type' => FieldType::DATE,
        ]);

        $taskFields['estimate'] = $tasksTable->fields()->create([
            'name' => 'Estymacja (h)',
            'type' => FieldType::NUMBER,
        ]);

        $taskFields['completed'] = $tasksTable->fields()->create([
            'name' => 'Zakończone',
            'type' => FieldType::CHECKBOX,
        ]);

        $taskFields['description'] = $tasksTable->fields()->create([
            'name' => 'Opis',
            'type' => FieldType::TEXT,
        ]);

        $tasks = [
            ['title' => 'Projekt bazy danych', 'project' => 'Portal klienta TechCorp', 'assignee' => 'Marek Kowalski', 'status' => 'Gotowe', 'priority' => 'Wysoki', 'due_date' => '2026-01-15', 'estimate' => 16, 'completed' => true, 'description' => 'Zaprojektować schemat bazy danych dla modułu klientów'],
            ['title' => 'API autoryzacji', 'project' => 'Portal klienta TechCorp', 'assignee' => 'Anna Nowak', 'status' => 'W trakcie', 'priority' => 'Wysoki', 'due_date' => '2026-02-01', 'estimate' => 24, 'completed' => false, 'description' => 'Implementacja JWT i OAuth2'],
            ['title' => 'Komponenty UI', 'project' => 'Portal klienta TechCorp', 'assignee' => 'Piotr Wiśniewski', 'status' => 'W trakcie', 'priority' => 'Średni', 'due_date' => '2026-02-10', 'estimate' => 40, 'completed' => false, 'description' => 'Stworzenie biblioteki komponentów Vue'],
            ['title' => 'Testy E2E', 'project' => 'Portal klienta TechCorp', 'assignee' => 'Marek Kowalski', 'status' => 'Do zrobienia', 'priority' => 'Niski', 'due_date' => '2026-03-01', 'estimate' => 20, 'completed' => false, 'description' => 'Napisanie testów end-to-end'],
            ['title' => 'Mockupy aplikacji', 'project' => 'Aplikacja mobilna FinGroup', 'assignee' => 'Ewa Dąbrowska', 'status' => 'Review', 'priority' => 'Wysoki', 'due_date' => '2026-02-05', 'estimate' => 32, 'completed' => false, 'description' => 'Projekt UX/UI aplikacji mobilnej'],
            ['title' => 'Optymalizacja CSS', 'project' => 'Redesign strony MediaHouse', 'assignee' => 'Piotr Wiśniewski', 'status' => 'Gotowe', 'priority' => 'Średni', 'due_date' => '2026-01-25', 'estimate' => 8, 'completed' => true, 'description' => 'Refaktor stylów i tree-shaking'],
            ['title' => 'SEO audit', 'project' => 'Redesign strony MediaHouse', 'assignee' => 'Anna Nowak', 'status' => 'W trakcie', 'priority' => 'Średni', 'due_date' => '2026-02-15', 'estimate' => 12, 'completed' => false, 'description' => 'Analiza i optymalizacja SEO'],
            ['title' => 'Dokumentacja API', 'project' => 'API integracyjne', 'assignee' => 'Marek Kowalski', 'status' => 'Zablokowane', 'priority' => 'Niski', 'due_date' => '2026-02-20', 'estimate' => 16, 'completed' => false, 'description' => 'Swagger/OpenAPI dokumentacja'],
        ];

        foreach ($tasks as $taskData) {
            $row = $tasksTable->rows()->create();
            $this->setCellValues($row, $taskFields, $taskData);
        }

        // ========== TEAM TABLE ==========
        $teamTable = $base->tables()->create([
            'name' => 'Zespół',
            'description' => 'Członkowie zespołu',
        ]);

        $teamFields = [];
        $teamFields['name'] = $teamTable->fields()->create([
            'name' => 'Imię i nazwisko',
            'type' => FieldType::TEXT,
            'is_primary' => true,
        ]);

        $teamFields['email'] = $teamTable->fields()->create([
            'name' => 'Email',
            'type' => FieldType::TEXT,
        ]);

        $teamFields['role'] = $teamTable->fields()->create([
            'name' => 'Rola',
            'type' => FieldType::SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Project Manager', 'color' => '#8B5CF6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Backend Developer', 'color' => '#3B82F6'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Frontend Developer', 'color' => '#10B981'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'UX/UI Designer', 'color' => '#F59E0B'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'QA Engineer', 'color' => '#EF4444'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'DevOps', 'color' => '#0EA5E9'],
                ],
            ],
        ]);

        $teamFields['skills'] = $teamTable->fields()->create([
            'name' => 'Umiejętności',
            'type' => FieldType::MULTI_SELECT,
            'options' => [
                'choices' => [
                    ['id' => Str::ulid()->toBase32(), 'name' => 'PHP', 'color' => '#777BB4'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'JavaScript', 'color' => '#F7DF1E'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Python', 'color' => '#3776AB'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'SQL', 'color' => '#336791'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Docker', 'color' => '#2496ED'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Figma', 'color' => '#F24E1E'],
                    ['id' => Str::ulid()->toBase32(), 'name' => 'Scrum', 'color' => '#6DB33F'],
                ],
            ],
        ]);

        $teamFields['hourly_rate'] = $teamTable->fields()->create([
            'name' => 'Stawka (PLN/h)',
            'type' => FieldType::NUMBER,
        ]);

        $teamFields['available'] = $teamTable->fields()->create([
            'name' => 'Dostępny',
            'type' => FieldType::CHECKBOX,
        ]);

        $teamFields['start_date'] = $teamTable->fields()->create([
            'name' => 'Data dołączenia',
            'type' => FieldType::DATE,
        ]);

        $teamFields['linkedin'] = $teamTable->fields()->create([
            'name' => 'LinkedIn',
            'type' => FieldType::URL,
        ]);

        $team = [
            ['name' => 'Jan Nowak', 'email' => 'jan@example.com', 'role' => 'Project Manager', 'skills' => ['Scrum', 'SQL'], 'hourly_rate' => 200, 'available' => true, 'start_date' => '2023-01-15', 'linkedin' => 'https://linkedin.com/in/jannowak'],
            ['name' => 'Marek Kowalski', 'email' => 'marek@example.com', 'role' => 'Backend Developer', 'skills' => ['PHP', 'SQL', 'Docker'], 'hourly_rate' => 180, 'available' => true, 'start_date' => '2023-03-01', 'linkedin' => 'https://linkedin.com/in/marekkowalski'],
            ['name' => 'Anna Nowak', 'email' => 'anna.n@example.com', 'role' => 'Backend Developer', 'skills' => ['PHP', 'Python', 'SQL'], 'hourly_rate' => 170, 'available' => true, 'start_date' => '2023-06-15', 'linkedin' => 'https://linkedin.com/in/annanowak'],
            ['name' => 'Piotr Wiśniewski', 'email' => 'piotr@example.com', 'role' => 'Frontend Developer', 'skills' => ['JavaScript', 'Docker'], 'hourly_rate' => 160, 'available' => true, 'start_date' => '2024-01-10', 'linkedin' => 'https://linkedin.com/in/piotrwisniewski'],
            ['name' => 'Ewa Dąbrowska', 'email' => 'ewa@example.com', 'role' => 'UX/UI Designer', 'skills' => ['Figma'], 'hourly_rate' => 150, 'available' => false, 'start_date' => '2024-04-01', 'linkedin' => 'https://linkedin.com/in/ewadabrowska'],
        ];

        foreach ($team as $memberData) {
            $row = $teamTable->rows()->create();
            $this->setCellValues($row, $teamFields, $memberData);
        }
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
