<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            direction: rtl;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2d6a4f;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 { color: #2d6a4f; font-size: 20px; margin: 0; }
        .header p  { color: #555; margin: 4px 0 0; font-size: 11px; }
        .section-title {
            background: #2d6a4f;
            color: #fff;
            padding: 5px 10px;
            font-size: 13px;
            margin: 15px 0 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        th {
            background: #e9f5ee;
            border: 1px solid #b7dfc8;
            padding: 6px 8px;
            text-align: right;
            font-size: 11px;
        }
        td {
            border: 1px solid #ddd;
            padding: 5px 8px;
            font-size: 11px;
        }
        tr:nth-child(even) td { background: #f9f9f9; }
        .info-grid {
            width: 100%;
            margin-bottom: 10px;
        }
        .info-grid td {
            border: none;
            padding: 3px 8px;
            width: 50%;
        }
        .label { color: #555; font-size: 10px; }
        .value { font-weight: bold; }
        .badge-awarded {
            background: #2d6a4f;
            color: #fff;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
        }
        .total-row td {
            background: #e9f5ee;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 8px;
            text-align: center;
            color: #888;
            font-size: 10px;
        }
        .checkmark { color: #2d6a4f; }
    </style>
</head>
<body>

<div class="header">
    <h1>🏥 PharmaLink — تقرير المناقصة النهائي</h1>
    <p>تاريخ الإصدار: {{ $generatedAt }}</p>
</div>

{{-- معلومات المناقصة --}}
<div class="section-title">معلومات المناقصة</div>
<table class="info-grid">
    <tr>
        <td><span class="label">عنوان المناقصة</span><br><span class="value">{{ $rfq->title }}</span></td>
        <td><span class="label">الصيدلية</span><br><span class="value">{{ $rfq->pharmacy->name ?? '—' }}</span></td>
    </tr>
    <tr>
        <td><span class="label">الحالة</span><br><span class="badge-awarded">مُرسَّاة</span></td>
        <td><span class="label">تاريخ الإنشاء</span><br><span class="value">{{ $rfq->created_at->format('Y-m-d') }}</span></td>
    </tr>
    <tr>
        <td><span class="label">آخر موعد للعروض</span><br><span class="value">{{ $rfq->quotes_deadline_at->format('Y-m-d H:i') }}</span></td>
        <td><span class="label">تاريخ فتح المظاريف</span><br><span class="value">{{ $rfq->opening_at->format('Y-m-d H:i') }}</span></td>
    </tr>
</table>

{{-- الأصناف المطلوبة --}}
<div class="section-title">الأصناف المطلوبة</div>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>اسم المنتج</th>
            <th>الفئة</th>
            <th>الكمية المطلوبة</th>
            <th>ملاحظات</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rfq->rfqItems as $i => $item)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $item->product->name ?? '—' }}</td>
            <td>{{ $item->product->category ?? '—' }}</td>
            <td>{{ number_format($item->quantity) }}</td>
            <td>{{ $item->notes ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

@if($winningQuote)
{{-- العرض الفائز --}}
<div class="section-title">العرض الفائز — {{ $winningQuote->supplier->name ?? '—' }}</div>
<table class="info-grid">
    <tr>
        <td><span class="label">اسم الشركة</span><br><span class="value">{{ $winningQuote->supplier->name ?? '—' }}</span></td>
        <td><span class="label">حالة الشركة</span><br>
            @if($winningQuote->supplier->is_verified)
                <span class="checkmark">✔</span> شركة معتمدة
            @else
                غير معتمدة
            @endif
        </td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>المنتج</th>
            <th>سعر الوحدة</th>
            <th>نسبة الخصم</th>
            <th>السعر الصافي</th>
            <th>الكمية المتاحة</th>
            <th>مدة التوريد</th>
            <th>ملاحظات</th>
        </tr>
    </thead>
    <tbody>
        @foreach($winningQuote->quoteItems as $item)
        <tr>
            <td>{{ $item->product->name ?? '—' }}</td>
            <td>{{ number_format((float)$item->unit_price, 2) }}</td>
            <td>{{ $item->discount_percentage }}%</td>
            <td>{{ number_format((float)$item->net_unit_price, 2) }}</td>
            <td>{{ $item->available_qty ? number_format($item->available_qty) : '—' }}</td>
            <td>{{ $item->delivery_days }} يوم</td>
            <td>{{ $item->notes ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@if($purchaseOrder)
{{-- أمر الشراء --}}
<div class="section-title">أمر الشراء</div>
<table class="info-grid">
    <tr>
        <td><span class="label">رقم أمر الشراء</span><br><span class="value">#{{ $purchaseOrder->id }}</span></td>
        <td><span class="label">المورد</span><br><span class="value">{{ $purchaseOrder->supplier->name ?? '—' }}</span></td>
    </tr>
    <tr>
        <td><span class="label">الحالة</span><br><span class="value">{{ $purchaseOrder->status }}</span></td>
        <td><span class="label">الإجمالي الكلي</span><br><span class="value" style="font-size:14px;color:#2d6a4f;">
            {{ number_format((float)$purchaseOrder->total_amount, 2) }} ₪
        </span></td>
    </tr>
</table>
@endif

{{-- معايير التقييم المستخدمة --}}
<div class="section-title">معايير التقييم المستخدمة</div>
<table>
    <thead>
        <tr><th>المعيار</th><th>الوزن</th></tr>
    </thead>
    <tbody>
        <tr><td>أقل سعر</td><td>{{ $rfq->score_weight_price }}%</td></tr>
        <tr><td>أسرع توريد</td><td>{{ $rfq->score_weight_delivery }}%</td></tr>
        <tr><td>أعلى تقييم سابق</td><td>{{ $rfq->score_weight_rating }}%</td></tr>
        <tr><td>شركة معتمدة</td><td>{{ $rfq->score_weight_verified }}%</td></tr>
        <tr class="total-row"><td>المجموع</td><td>100%</td></tr>
    </tbody>
</table>

<div class="footer">
    تم إصدار هذا التقرير تلقائيًا من نظام PharmaLink &mdash; {{ $generatedAt }}
</div>

</body>
</html>
