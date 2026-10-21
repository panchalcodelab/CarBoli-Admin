<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Customer;
use App\Models\Inspection;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $validated = Validator::make($request->query(), [
            'type' => ['required', 'in:auctions,payments,customers,inspections'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ])->validate();

        $type = $validated['type'];
        Gate::authorize($type === 'customers' ? 'view customers' : 'view financial reports');

        [$model, $columns, $dateColumn] = $this->reportDefinition($type);
        $query = $model::query()->select($columns);

        if ($type === 'customers') {
            $query->whereNull('deleted_at');
        }

        if (filled($validated['from'] ?? null)) {
            $query->whereDate($dateColumn, '>=', $validated['from']);
        }

        if (filled($validated['to'] ?? null)) {
            $query->whereDate($dateColumn, '<=', $validated['to']);
        }

        return response()->streamDownload(function () use ($query, $columns): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, $columns);

            $query->orderBy('id')->chunkById(500, function ($records) use ($output, $columns): void {
                foreach ($records as $record) {
                    fputcsv($output, array_map(function (string $column) use ($record): mixed {
                        $value = $record->getAttribute($column);

                        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)
                            ? "'".$value
                            : $value;
                    }, $columns));
                }
            });

            fclose($output);
        }, "car-boli-{$type}-report.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{class-string, list<string>, string}
     */
    private function reportDefinition(string $type): array
    {
        return match ($type) {
            'auctions' => [Auction::class, ['id', 'title', 'car_id', 'status', 'start_at', 'end_at', 'starting_price', 'winner_id', 'created_at'], 'created_at'],
            'payments' => [Payment::class, ['id', 'auction_id', 'customer_id', 'payment_type', 'amount', 'currency', 'status', 'captured_at', 'created_at'], 'created_at'],
            'customers' => [Customer::class, ['id', 'full_name', 'email', 'account_status', 'kyc_status', 'created_at'], 'created_at'],
            'inspections' => [Inspection::class, ['id', 'car_id', 'customer_id', 'assigned_staff_id', 'inspection_date', 'status', 'created_at'], 'created_at'],
            default => throw ValidationException::withMessages(['type' => 'Unsupported report type.']),
        };
    }
}
