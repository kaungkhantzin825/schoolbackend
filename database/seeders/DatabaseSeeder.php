<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\University;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@system.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        $defaultNotice = "Students who completed their courses or graduated in 2023, 2024, or 2025 will receive instant verification results through the system. For older or archived academic records, manual verification by the university's registrar team is required. Credential searches and processing may take approximately 2 to 5 business days.";

        // Create Universities
        $um1 = University::create([
            'name' => 'University of Medicine 1',
            'location' => 'Yangon',
            'description' => 'The University of Medicine 1, Yangon (UM1) is the oldest and most prestigious medical school in Myanmar. Established on February 2, 1927, it has a long-standing history of excellence and serves as a cornerstone for the country\'s healthcare system.',
            'logo_url' => 'https://upload.wikimedia.org/wikipedia/en/thumb/8/87/University_of_Medicine_1%2C_Yangon_logo.png/150px-University_of_Medicine_1%2C_Yangon_logo.png',
            'status' => 'active',
            'verification_notice' => $defaultNotice,
        ]);

        $um2 = University::create([
            'name' => 'University of Medicine 2',
            'location' => 'Yangon',
            'description' => 'University of Medicine 2, Yangon is a leading medical institution in Myanmar.',
            'logo_url' => 'https://upload.wikimedia.org/wikipedia/en/thumb/f/f6/UM2ygn.png/150px-UM2ygn.png',
            'status' => 'active',
            'verification_notice' => $defaultNotice,
        ]);

        $umMandalay = University::create([
            'name' => 'University of Medicine',
            'location' => 'Mandalay',
            'description' => 'University of Medicine, Mandalay is a premier medical school in Upper Myanmar.',
            'logo_url' => 'https://upload.wikimedia.org/wikipedia/my/thumb/3/30/UOM_MDY.png/220px-UOM_MDY.png',
            'status' => 'active',
            'verification_notice' => $defaultNotice,
        ]);

        $techUni = University::create([
            'name' => 'Technological University',
            'location' => 'Mandalay',
            'description' => 'Technological University, Mandalay offers engineering and technology programs.',
            'logo_url' => 'https://upload.wikimedia.org/wikipedia/en/thumb/0/07/MTU%2C_Myanmar_logo-3.png/200px-MTU%2C_Myanmar_logo-3.png',
            'status' => 'active',
            'verification_notice' => $defaultNotice,
        ]);

        $umMagway = University::create([
            'name' => 'University of Medicine',
            'location' => 'Magway',
            'description' => 'University of Medicine, Magway provides quality medical education.',
            'status' => 'active',
            'verification_notice' => $defaultNotice,
        ]);

        // Create University Admins
        $um1Admin = User::create([
            'name' => 'John Smith',
            'email' => 'john@um1.edu',
            'password' => Hash::make('password123'),
            'role' => 'university_admin',
            'university_id' => $um1->id,
        ]);

        $techAdmin = User::create([
            'name' => 'Mary Johnson',
            'email' => 'mary@tech.edu',
            'password' => Hash::make('password123'),
            'role' => 'university_admin',
            'university_id' => $techUni->id,
        ]);

        // Create sample students for UM1
        Student::create([
            'university_id' => $um1->id,
            'graduate_name' => 'Maung Maung',
            'father_name' => 'U Kaung',
            'gender' => 'Male',
            'date_of_birth' => '2000-03-05',
            'nrc_number' => '5/Kapana(N)32490',
            'student_id' => '02365',
            'degree' => 'M.Med.Sc',
            'specialization' => 'Master of Medical Science',
            'graduation_year' => 2023,
        ]);

        Student::create([
            'university_id' => $um1->id,
            'graduate_name' => 'Su Su Hlaing',
            'father_name' => 'U Hla Win',
            'gender' => 'Female',
            'date_of_birth' => '1999-07-15',
            'nrc_number' => '12/Oukama(N)45678',
            'student_id' => '02366',
            'degree' => 'MBBS',
            'specialization' => 'Bachelor of Medicine',
            'graduation_year' => 2022,
        ]);

        // Create sample students for Tech University
        $techStudents = [
            ['Mg Mg', 'U Tin', 'Male', '2000-01-01', '5/Kapana(N)20000', 'TU001', 'B.E', 'Petroleum', 2023],
            ['Ko Ko', 'U Aung', 'Male', '1999-06-20', '9/Mayaka(N)54321', 'TU002', 'B.E', 'Mechanical', 2022],
            ['Su Su', 'U Kyaw', 'Female', '2001-03-15', '12/Oukama(N)12345', 'TU003', 'B.E', 'Civil', 2024],
            ['Hla Hla', 'U Win', 'Female', '2002-05-18', '14/Dakana(N)98765', 'TU004', 'B.E', 'Electrical', 2025],
            ['Zaw Zaw', 'U Myint', 'Male', '1998-11-30', '8/Phenma(N)11223', 'TU005', 'B.E', 'Civil', 2021],
            ['Aye Aye', 'U Hlaing', 'Female', '2000-08-25', '1/Bagana(N)44556', 'TU006', 'B.E', 'Architecture', 2023],
            ['Nyi Nyi', 'U Thein', 'Male', '1999-02-14', '3/Bago(N)77889', 'TU007', 'B.E', 'Computer', 2022],
            ['Myo Myo', 'U Lwin', 'Female', '2001-09-09', '7/Yegyi(N)00112', 'TU008', 'B.E', 'Mechanical', 2024],
            ['Kyaw Kyaw', 'U Tun', 'Male', '2000-12-12', '11/TadaU(N)33445', 'TU009', 'B.E', 'Electrical', 2023],
            ['Thuzar', 'U Soe', 'Female', '1999-04-03', '6/Mawla(N)66778', 'TU010', 'B.E', 'Civil', 2022],
        ];

        foreach ($techStudents as $s) {
            Student::create([
                'university_id' => $techUni->id,
                'graduate_name' => $s[0],
                'father_name' => $s[1],
                'gender' => $s[2],
                'date_of_birth' => $s[3],
                'nrc_number' => $s[4],
                'student_id' => $s[5],
                'degree' => $s[6],
                'specialization' => $s[7],
                'graduation_year' => $s[8],
            ]);
        }

        // Add verification logs
        \App\Models\VerificationLog::insert([
            [
                'university_id' => $techUni->id,
                'student_id' => 3, // Mg Mg
                'verifier_name' => 'John Smith',
                'organization_type' => 'Employer',
                'organization_name' => 'Construction Co.',
                'searched_name' => 'Mg Mg',
                'searched_father_name' => 'U Tin',
                'searched_degree' => 'B.E',
                'searched_year' => 2023,
                'result' => 'verified',
                'status' => 'success',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'university_id' => $techUni->id,
                'student_id' => null,
                'verifier_name' => 'Mary Jane',
                'organization_type' => 'Recruitment Agency',
                'organization_name' => 'TechHunt',
                'searched_name' => 'Unknown Student',
                'searched_father_name' => 'U Unknown',
                'searched_degree' => 'B.E',
                'searched_year' => 2021,
                'result' => 'not_found',
                'status' => 'failed',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],
            [
                'university_id' => $um1->id,
                'student_id' => 1, 
                'verifier_name' => 'Dr. Wilson',
                'organization_type' => 'Hospital',
                'organization_name' => 'Yangon General Hospital',
                'searched_name' => 'Maung Maung',
                'searched_father_name' => 'U Kaung',
                'searched_degree' => 'M.Med.Sc',
                'searched_year' => 2023,
                'result' => 'verified',
                'status' => 'success',
                'created_at' => now()->subHours(5),
                'updated_at' => now()->subHours(5),
            ],
            [
                'university_id' => $techUni->id,
                'student_id' => 4, // Ko Ko
                'verifier_name' => 'HR Manager',
                'organization_type' => 'Company',
                'organization_name' => 'Global Tech',
                'searched_name' => 'Ko Ko',
                'searched_father_name' => 'U Aung',
                'searched_degree' => 'B.E',
                'searched_year' => 2022,
                'result' => 'verified',
                'status' => 'success',
                'created_at' => now()->subMinutes(30),
                'updated_at' => now()->subMinutes(30),
            ]
        ]);

        // Create Verifier User (Mr. Smith)
        $verifierUser = User::firstOrCreate(
            ['email' => 'smith@verifier.com'],
            [
                'name' => 'Mr. Smith',
                'password' => Hash::make('password123'),
                'role' => 'verifier',
            ]
        );

        // Add exact students for Image 1 (Verified Records)
        $s1 = Student::updateOrCreate(
            ['nrc_number' => '5/Kapana(N)32490'],
            [
                'university_id' => $um1->id,
                'graduate_name' => 'Maung Maung',
                'father_name' => 'U Kaung',
                'gender' => 'Male',
                'date_of_birth' => '2020-03-05',
                'student_id' => '098777',
                'degree' => 'M.Med.Sc',
                'specialization' => 'Master of Medical Science',
                'graduation_year' => 2023,
            ]
        );

        $s2 = Student::updateOrCreate(
            ['nrc_number' => '5/Kapana(N)22476'],
            [
                'university_id' => $techUni->id,
                'graduate_name' => 'Aung Aung',
                'father_name' => 'U Hla',
                'gender' => 'Male',
                'date_of_birth' => '2020-02-23',
                'student_id' => '09763',
                'degree' => 'B.E',
                'specialization' => 'Petroleum',
                'graduation_year' => 2023,
            ]
        );

        $s3 = Student::updateOrCreate(
            ['nrc_number' => '5/Kapana(N)29523'],
            [
                'university_id' => $techUni->id,
                'graduate_name' => 'Bo Bo',
                'father_name' => 'U Kyaw',
                'gender' => 'Male',
                'date_of_birth' => '2020-08-08',
                'student_id' => '07633',
                'degree' => 'B.E',
                'specialization' => 'Civil',
                'graduation_year' => 2025,
            ]
        );

        // Verified Records (Image 1)
        \App\Models\VerificationLog::updateOrCreate(
            ['request_ref' => 'VR-VERIFIED-01'],
            [
                'university_id' => $um1->id,
                'student_id' => $s1->id,
                'request_ref' => 'VR-VERIFIED-01',
                'verifier_name' => 'Mr. Smith',
                'verifier_email' => 'smith@verifier.com',
                'organization_type' => 'Employer',
                'organization_name' => 'Frontiir Tech & Healthcare',
                'searched_name' => 'Maung Maung',
                'searched_father_name' => 'U Kaung',
                'searched_degree' => 'M.Med.Sc',
                'searched_year' => 2023,
                'result' => 'verified',
                'status' => 'success',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]
        );

        \App\Models\VerificationLog::updateOrCreate(
            ['request_ref' => 'VR-VERIFIED-02'],
            [
                'university_id' => $techUni->id,
                'student_id' => $s2->id,
                'request_ref' => 'VR-VERIFIED-02',
                'verifier_name' => 'Mr. Smith',
                'verifier_email' => 'smith@verifier.com',
                'organization_type' => 'Employer',
                'organization_name' => 'Frontiir Tech & Healthcare',
                'searched_name' => 'Aung Aung',
                'searched_father_name' => 'U Hla',
                'searched_degree' => 'B.E(Petroleum)',
                'searched_year' => 2023,
                'result' => 'verified',
                'status' => 'success',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]
        );

        \App\Models\VerificationLog::updateOrCreate(
            ['request_ref' => 'VR-VERIFIED-03'],
            [
                'university_id' => $techUni->id,
                'student_id' => $s3->id,
                'request_ref' => 'VR-VERIFIED-03',
                'verifier_name' => 'Mr. Smith',
                'verifier_email' => 'smith@verifier.com',
                'organization_type' => 'Employer',
                'organization_name' => 'Frontiir Tech & Healthcare',
                'searched_name' => 'Bo Bo',
                'searched_father_name' => 'U Kyaw',
                'searched_degree' => 'B.E(Civil)',
                'searched_year' => 2025,
                'result' => 'verified',
                'status' => 'success',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ]
        );

        // Pending Requests (Image 2)
        \App\Models\VerificationLog::updateOrCreate(
            ['request_ref' => 'VR-1034'],
            [
                'university_id' => $techUni->id,
                'student_id' => null,
                'request_ref' => 'VR-1034',
                'verifier_name' => 'Mr. Smith',
                'verifier_email' => 'smith@verifier.com',
                'organization_type' => 'Employer',
                'organization_name' => 'Frontiir Tech & Healthcare',
                'searched_name' => 'Maung Maung',
                'searched_father_name' => 'U Ba',
                'searched_degree' => 'B.E.(Civil)',
                'searched_year' => 2020,
                'result' => 'not_found',
                'status' => 'pending',
                'sla_due_at' => now()->addDay(),
                'created_at' => '2024-03-12 10:00:00',
                'updated_at' => '2024-03-12 10:00:00',
            ]
        );

        \App\Models\VerificationLog::updateOrCreate(
            ['request_ref' => 'VR-1035'],
            [
                'university_id' => $techUni->id,
                'student_id' => null,
                'request_ref' => 'VR-1035',
                'verifier_name' => 'Mr. Smith',
                'verifier_email' => 'smith@verifier.com',
                'organization_type' => 'Employer',
                'organization_name' => 'Frontiir Tech & Healthcare',
                'searched_name' => 'Aung Aung',
                'searched_father_name' => 'U Tin',
                'searched_degree' => 'B.Sc.(Computer)',
                'searched_year' => 2019,
                'result' => 'not_found',
                'status' => 'pending',
                'sla_due_at' => now()->addDays(2),
                'created_at' => '2024-03-13 11:30:00',
                'updated_at' => '2024-03-13 11:30:00',
            ]
        );

        \App\Models\VerificationLog::updateOrCreate(
            ['request_ref' => 'VR-1036'],
            [
                'university_id' => $techUni->id,
                'student_id' => null,
                'request_ref' => 'VR-1036',
                'verifier_name' => 'Mr. Smith',
                'verifier_email' => 'smith@verifier.com',
                'organization_type' => 'Employer',
                'organization_name' => 'Frontiir Tech & Healthcare',
                'searched_name' => 'Bo Bo',
                'searched_father_name' => 'U Kyaw',
                'searched_degree' => 'M.A.(History)',
                'searched_year' => 2018,
                'result' => 'not_found',
                'status' => 'pending',
                'sla_due_at' => now()->addDays(5),
                'created_at' => '2024-03-14 09:15:00',
                'updated_at' => '2024-03-14 09:15:00',
            ]
        );

        \App\Models\VerificationLog::updateOrCreate(
            ['request_ref' => 'VR-1037'],
            [
                'university_id' => $um1->id,
                'student_id' => null,
                'request_ref' => 'VR-1037',
                'verifier_name' => 'Dr. Wilson',
                'verifier_email' => 'wilson@ygh.gov.mm',
                'organization_type' => 'Hospital',
                'organization_name' => 'Yangon General Hospital',
                'searched_name' => 'Su Su Hlaing',
                'searched_father_name' => 'U Hla Win',
                'searched_degree' => 'MBBS',
                'searched_year' => 2018,
                'result' => 'not_found',
                'status' => 'pending',
                'sla_due_at' => now()->addDays(3),
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ]
        );

        $this->command->info('Database seeded successfully!');
        $this->command->info('Super Admin: superadmin@system.com / password123');
        $this->command->info('UM1 Admin: john@um1.edu / password123');
        $this->command->info('Tech Admin: mary@tech.edu / password123');
        $this->command->info('Verifier: smith@verifier.com / password123');
    }
}
