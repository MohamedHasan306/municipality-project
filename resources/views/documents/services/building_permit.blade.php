<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $serviceType->name }} - {{ $documentNumber }}</title>
    <style>
        @page {
            margin: 28px 34px;
        }

        body {
            color: #1f2937;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            line-height: 1.55;
        }

        .header-table,
        .meta-table,
        .data-table,
        .signature-table {
            border-collapse: collapse;
            width: 100%;
        }

        .header-table td {
            vertical-align: middle;
        }

        .header-title {
            font-size: 21px;
            font-weight: bold;
            margin: 0 0 4px;
        }

        .header-subtitle {
            color: #4b5563;
            font-size: 12px;
        }

        .document-number {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: center;
        }

        .section-title {
            background: #eef2f7;
            border-left: 4px solid #334155;
            font-size: 13px;
            font-weight: bold;
            margin: 18px 0 8px;
            padding: 7px 9px;
        }

        .meta-table td {
            border-bottom: 1px solid #e5e7eb;
            padding: 6px 8px;
            vertical-align: top;
        }

        .meta-label {
            color: #475569;
            font-weight: bold;
            width: 24%;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #d7dde5;
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
        }

        .data-table th {
            background: #f8fafc;
            color: #334155;
            font-weight: bold;
        }

        .data-label {
            width: 35%;
        }

        .verification-box {
            border: 1px solid #cbd5e1;
            margin-top: 18px;
            padding: 10px;
        }

        .qr-cell {
            text-align: center;
            vertical-align: middle;
            width: 155px;
        }

        .qr-cell img {
            height: 135px;
            width: 135px;
        }

        .verification-code,
        .verification-url {
            font-family: monospace;
            font-size: 8px;
            word-break: break-all;
        }

        .signature-table td {
            padding: 8px;
            vertical-align: top;
        }

        .footer {
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 9px;
            margin-top: 22px;
            padding-top: 7px;
            text-align: center;
        }
    </style>
</head>
<body>
<table class="header-table">
    <tr>
        <td>
            <div class="header-title">{{ $municipality->name }}</div>
            <div class="header-subtitle">{{ $serviceType->name }}</div>
        </td>
        <td style="width: 220px;">
            <div class="document-number">
                <strong>Document Number</strong><br>
                {{ $documentNumber }}
            </div>
        </td>
    </tr>
</table>

<div class="section-title">Document Information</div>
<table class="meta-table">
    <tr>
        <td class="meta-label">Service</td>
        <td>{{ $serviceType->name }}</td>
        <td class="meta-label">Version</td>
        <td>{{ $version->version_number }}</td>
    </tr>
    <tr>
        <td class="meta-label">Request Number</td>
        <td>{{ $serviceRequest->id }}</td>
        <td class="meta-label">Issued At</td>
        <td>{{ $issuedAt->format('Y-m-d H:i:s') }}</td>
    </tr>
    <tr>
        <td class="meta-label">Expires At</td>
        <td>{{ $expiresAt->format('Y-m-d H:i:s') }}</td>
        <td class="meta-label">Municipality</td>
        <td>{{ $municipality->name }}</td>
    </tr>
    <tr>
        <td class="meta-label">Municipality Address</td>
        <td>{{ $municipality->address }}</td>
        <td class="meta-label">Municipality Phone</td>
        <td>{{ $municipality->phone }}</td>
    </tr>
    <tr>
        <td class="meta-label">Municipality Email</td>
        <td colspan="3">{{ $municipality->email }}</td>
    </tr>
</table>

<div class="section-title">Citizen Information</div>
<table class="meta-table">
    <tr>
        <td class="meta-label">Full Name</td>
        <td>{{ $citizenUser->full_name }}</td>
        <td class="meta-label">National ID</td>
        <td>{{ $citizenProfile->national_id }}</td>
    </tr>
</table>

<div class="section-title">Request Data</div>
<table class="data-table">
    <thead>
    <tr>
        <th class="data-label">Field</th>
        <th>Value</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($fields as $field)
        @php
            $value = $field->field_type === 'file'
                ? $attachments->get($field->field_key)?->original_name
                : ($values[$field->field_key] ?? null);

            if ($field->field_type === 'checkbox' && $value !== null) {
                $value = $value ? 'Yes' : 'No';
            } elseif (is_array($value)) {
                $value = implode(', ', $value);
            }
        @endphp
        <tr>
            <td>{{ $field->label }}</td>
            <td>{{ $value === null || $value === '' ? 'Not provided' : $value }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="verification-box">
    <table class="signature-table">
        <tr>
            <td>
                <strong>Digital Signature Information</strong><br><br>
                Signed by: {{ $mayor->full_name }}<br>
                Signed at: {{ $signedAt->format('Y-m-d H:i:s') }}<br>
                Algorithm: {{ $signatureAlgorithm }}<br><br>
                <strong>Verification Code</strong><br>
                <span class="verification-code">{{ $verificationCode }}</span><br><br>
                <strong>Verification URL</strong><br>
                <span class="verification-url">{{ $verificationUrl }}</span><br><br>
                Scan the QR code while authenticated as a field inspector to verify the current
                file hash, detached digital signature, and expiration status.
            </td>
            <td class="qr-cell">
                <img src="{{ $qrCodeDataUri }}" alt="Document verification QR code">
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    This PDF is stored on private server storage. Authenticity is determined by recalculating
    its SHA-256 hash and validating the detached RSA signature with the configured public key.
</div>
</body>
</html>
