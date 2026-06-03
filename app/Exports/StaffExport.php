<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StaffExport implements FromCollection, WithHeadings
{
      public function collection()
    {
        return User::where('role', 'staff')->select('id', 'first_name', 'last_name', 'email', 'role', 'status', 'created_at')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'First Name',
            'Last Name',
            'Email',
            'Role',
            'Status',
            'Joined Date'
        ];
    }
}
