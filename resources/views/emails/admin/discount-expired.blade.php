<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Discount Expired</title>
</head>

<body>

    <h2>🚨 Discount Expired</h2>

    <p>
        A discount has expired and needs your attention.
    </p>

    <table border="1" cellpadding="10" cellspacing="0">
        <tr>
            <th>Discount ID</th>
            <td>{{ $discount->id }}</td>
        </tr>
        <tr>
            <th>Discount Name</th>
            <td>{{ $discount->name }}</td>
        </tr>

        <tr>
            <th>Description</th>
            <td>{{ $discount->description }}</td>
        </tr>

        <tr>
            <th>Type</th>
            <td>{{ $discount->type }}</td>
        </tr>

        <tr>
            <th>Amount</th>
            <td>{{ $discount->amount }}</td>
        </tr>

        <tr>
            <th>Start Date</th>
            <td>{{ $discount->start_date }}</td>
        </tr>

        <tr>
            <th>End Date</th>
            <td>{{ $discount->end_date }}</td>
        </tr>
    </table>

    <br>

    <p>
        Please check the admin dashboard.
    </p>

</body>

</html>