<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Return Update - Vendo</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        @media screen and (max-width: 600px) {
            .container {
                width: 100% !important;
            }

            .content-padding {
                padding: 30px 20px !important;
            }
        }
    </style>
</head>

<body style="background-color: #f8fafc; padding: 60px 10px;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
        <tr>
            <td align="center">
                <table class="container" border="0" cellpadding="0" cellspacing="0" width="100%"
                    style="max-width: 500px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0;">

                    <tr>
                        <td style="height: 4px; background-color: #2b3a55;"></td>
                    </tr>

                    <tr>
                        <td class="content-padding" style="padding: 48px 40px 32px; text-align: center;">
                            <div style="margin-bottom: 24px;">
                                <span
                                    style="display: inline-block; background-color: #f1f5f9; padding: 12px 20px; border-radius: 12px; font-weight: 800; color: #2b3a55; font-size: 18px; letter-spacing: 2px;">VENDO</span>
                            </div>
                            <h1
                                style="margin: 0; color: #1e293b; font-size: 24px; font-weight: 800; letter-spacing: -0.02em;">
                                Return Update</h1>
                            <p style="margin: 12px 0 0; color: #64748b; font-size: 15px; line-height: 1.6;">
                                Hello {{ $returnOrder->user->first_name }}, there has been a status change regarding
                                your return request for <strong>Order #{{ $returnOrder->order->order_number }}</strong>.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 32px; text-align: center;">
                            <span style="
                                display: inline-block;
                                padding: 8px 20px;
                                border-radius: 99px;
                                font-size: 12px;
                                font-weight: 800;
                                text-transform: uppercase;
                                letter-spacing: 1px;
                                background-color: {{ $returnOrder->status === 'approved' ? '#dcfce7' : '#fef2f2' }};
                                color: {{ $returnOrder->status === 'approved' ? '#166534' : '#991b1b' }};
                                border: 1px solid {{ $returnOrder->status === 'approved' ? '#bbf7d0' : '#fecaca' }};
                            ">
                                {{ $returnOrder->status }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 48px;">
                            @if($returnOrder->status === 'approved')
                                <div
                                    style="background-color: #f8fafc; border-radius: 12px; padding: 24px; border: 1px solid #f1f5f9;">
                                    <div style="color: #2b3a55; font-size: 14px; font-weight: 700; margin-bottom: 8px;">Next
                                        Steps</div>
                                    <p style="margin: 0; color: #475569; font-size: 14px; line-height: 1.6;">
                                        Please log in to your dashboard to download your <strong>Return Shipping
                                            Label</strong> and review the packaging instructions.
                                    </p>
                                    <div style="margin-top: 24px; text-align: center;">
                                        <a href="{{ url('/dashboard/returns') }}"
                                            style="display: inline-block; background-color: #2b3a55; color: #ffffff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px;">
                                            Get Return Label
                                        </a>
                                    </div>
                                </div>
                            @else
                                <div
                                    style="background-color: #fffafb; border-radius: 12px; padding: 24px; border: 1px solid #fef2f2;">
                                    <div style="color: #991b1b; font-size: 14px; font-weight: 700; margin-bottom: 8px;">
                                        Request Denied</div>
                                    <p style="margin: 0; color: #7f1d1d; font-size: 14px; line-height: 1.6;">
                                        Unfortunately, we were unable to approve this return request. For more details or to
                                        appeal this decision, please contact our support team.
                                    </p>
                                    <div style="margin-top: 24px; text-align: center;">
                                        <a href="mailto:support@vendo.com"
                                            style="display: inline-block; background-color: #991b1b; color: #ffffff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px;">
                                            Contact Support
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="padding: 32px 40px; background-color: #fcfcfc; border-top: 1px solid #f1f5f9; text-align: center;">
                            <p
                                style="margin: 0; color: #94a3b8; font-size: 12px; line-height: 1.6; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                                Sent with ❤️ from VENDO Team
                            </p>
                            <p style="margin: 4px 0 0; color: #cbd5e1; font-size: 11px;">
                                Phnom Penh, Cambodia
                            </p>
                        </td>
                    </tr>
                </table>

                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 500px;">
                    <tr>
                        <td style="padding: 30px; text-align: center; color: #cbd5e1; font-size: 11px;">
                            &copy; {{ date('Y') }} Vendo E-commerce. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>