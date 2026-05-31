<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportController extends Controller
{
    public function index()
    {
        $groups = Group::orderBy('name')->get();
        return view('import.index', compact('groups'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'csv_file'             => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
            'name_column'          => ['nullable', 'string'],
            'first_name_column'    => ['nullable', 'string'],
            'last_name_column'     => ['nullable', 'string'],
            'email_column'         => ['required', 'string'],
            'business_name_column' => ['nullable', 'string'],
            'website_column'       => ['nullable', 'string'],
            'groups'               => ['nullable', 'array'],
            'groups.*'             => ['exists:groups,id'],
        ]);

        $path = $request->file('csv_file')->getRealPath();
        $handle = fopen($path, 'r');

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'CSV file appears to be empty.'])->withInput();
        }

        // Strip BOM and whitespace from headers
        $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
        $headers = array_map('trim', $headers);

        $nameCol          = trim((string) $request->name_column);
        $firstNameCol     = trim((string) $request->first_name_column);
        $lastNameCol      = trim((string) $request->last_name_column);
        $emailCol         = trim((string) $request->email_column);
        $businessNameCol  = trim((string) $request->business_name_column);
        $websiteCol       = trim((string) $request->website_column);

        // Resolve name strategy: single column OR first+last
        $nameIndex      = $nameCol !== '' ? array_search($nameCol, $headers, true) : false;
        $firstNameIndex = $firstNameCol !== '' ? array_search($firstNameCol, $headers, true) : false;
        $lastNameIndex  = $lastNameCol !== '' ? array_search($lastNameCol, $headers, true) : false;

        $emailIndex        = array_search($emailCol, $headers, true);
        $businessNameIndex = $businessNameCol !== '' ? array_search($businessNameCol, $headers, true) : false;
        $websiteIndex      = $websiteCol !== '' ? array_search($websiteCol, $headers, true) : false;

        // Must have either a name column OR at least first_name column
        $hasName      = $nameIndex !== false;
        $hasFirstName = $firstNameIndex !== false;

        if (!$hasName && !$hasFirstName) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'No name column found in CSV. Headers detected: ' . implode(', ', $headers)])->withInput();
        }

        if ($emailIndex === false) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'Email column not found in CSV. Headers detected: ' . implode(', ', $headers)])->withInput();
        }

        $accountId = (int) ($request->user()?->account_id ?? 0);
        $total = 0;
        $imported = 0;
        $skipped = 0;
        $failedRows = [];   // [['row' => N, 'email' => '...', 'name' => '...', 'reasons' => [...]]]
        $rowNumber = 1;     // 1-based, header is row 0
        $seenInFile = [];
        $rowsToInsert = [];
        $emailToRowMeta = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) {
                continue;
            }

            $rowNumber++;
            $total++;
            $reasons = [];

            if ($hasName) {
                $name = trim((string)($row[$nameIndex] ?? ''));
            } else {
                $firstName = trim((string)($row[$firstNameIndex] ?? ''));
                $lastName  = $lastNameIndex !== false ? trim((string)($row[$lastNameIndex] ?? '')) : '';
                $name      = trim($firstName . ' ' . $lastName);
            }

            $email        = strtolower(trim((string)($row[$emailIndex] ?? '')));
            $businessName = $businessNameIndex !== false ? trim((string)($row[$businessNameIndex] ?? '')) : null;
            $website      = $websiteIndex !== false ? trim((string)($row[$websiteIndex] ?? '')) : null;

            if ($website && !preg_match('/^https?:\/\//i', $website)) {
                $website = 'https://' . $website;
            }

            $validator = Validator::make(
                ['name' => $name, 'email' => $email, 'website' => $website ?: null],
                [
                    'name'    => ['required', 'string', 'max:255'],
                    'email'   => ['required', 'email', 'max:255'],
                    'website' => ['nullable', 'url', 'max:255'],
                ]
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $msg) {
                    $reasons[] = $msg;
                }
            }

            if ($email !== '' && isset($seenInFile[$email])) {
                $reasons[] = 'Duplicate email in this file';
            }

            if (!empty($reasons)) {
                $skipped++;
                $failedRows[] = [
                    'row'     => $rowNumber,
                    'name'    => $name !== '' ? $name : '—',
                    'email'   => $email !== '' ? $email : '—',
                    'reasons' => $reasons,
                ];
                continue;
            }

            $seenInFile[$email] = true;

            $rowsToInsert[] = [
                'account_id'    => $accountId,
                'name'          => $name,
                'business_name' => $businessName ?: null,
                'email'         => $email,
                'website'       => $website ?: null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            $emailToRowMeta[$email] = [
                'row' => $rowNumber,
                'name' => $name !== '' ? $name : '—',
            ];
        }

        if (!empty($rowsToInsert)) {
            $candidateEmails = array_values(array_unique(array_column($rowsToInsert, 'email')));

            $existingEmails = [];
            foreach (array_chunk($candidateEmails, 500) as $chunk) {
                $query = Contact::query()->whereIn('email', $chunk);

                if ($accountId > 0) {
                    $query->where('account_id', $accountId);
                }

                $found = $query->pluck('email')->all();

                foreach ($found as $existingEmail) {
                    $existingEmails[$existingEmail] = true;
                }
            }

            $insertRows = [];
            foreach ($rowsToInsert as $row) {
                if (isset($existingEmails[$row['email']])) {
                    $skipped++;
                    $failedRows[] = [
                        'row' => $emailToRowMeta[$row['email']]['row'] ?? '—',
                        'name' => $emailToRowMeta[$row['email']]['name'] ?? '—',
                        'email' => $row['email'],
                        'reasons' => ['Email already exists in contacts'],
                    ];
                    continue;
                }

                $insertRows[] = $row;
            }

            if (!empty($insertRows)) {
                DB::table('contacts')->insertOrIgnore($insertRows);

                $insertedContactsQuery = Contact::query()
                    ->whereIn('email', array_column($insertRows, 'email'));

                if ($accountId > 0) {
                    $insertedContactsQuery->where('account_id', $accountId);
                }

                $insertedContacts = $insertedContactsQuery->get(['id', 'email']);

                $imported = $insertedContacts->count();

                $groupIds = $request->groups ?? [];
                if (!empty($groupIds) && $imported > 0) {
                    $pivotRows = [];
                    foreach ($insertedContacts as $contact) {
                        foreach ($groupIds as $groupId) {
                            $pivotRows[] = [
                                'contact_id' => $contact->id,
                                'group_id' => (int) $groupId,
                            ];
                        }
                    }

                    if (!empty($pivotRows)) {
                        DB::table('contact_group')->insertOrIgnore($pivotRows);
                    }
                }

                $insertedEmailSet = array_flip($insertedContacts->pluck('email')->all());
                foreach ($insertRows as $row) {
                    if (!isset($insertedEmailSet[$row['email']])) {
                        $skipped++;
                        $failedRows[] = [
                            'row' => $emailToRowMeta[$row['email']]['row'] ?? '—',
                            'name' => $emailToRowMeta[$row['email']]['name'] ?? '—',
                            'email' => $row['email'],
                            'reasons' => ['Email already exists in contacts'],
                        ];
                    }
                }
            }
        }

        fclose($handle);

        return view('import.result', [
            'total'      => $total,
            'imported'   => $imported,
            'skipped'    => $skipped,
            'failedRows' => $failedRows,
        ]);
    }
}
