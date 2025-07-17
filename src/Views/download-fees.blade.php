<table width="100%" style="margin-bottom: 20px;">
	<tr>
		<th width="12%" style="text-align: left;">Process</th>
		<td width="88%">{{ $data['signature'] }}</td>
	</tr>
	<tr>
		<th style="text-align: left;">Process Start</th>
		<td>{{ $data['process_start'] }}</td>
	</tr>
	<tr>
		<th style="text-align: left;">Process End</th>
		<td>{{ $data['process_end'] }}</td>
	</tr>
	<tr>
		<th style="text-align: left;">Total Count</th>
		<td>{{ $data['total_count'] }}</td>
	</tr>
	<tr>
		<th style="text-align: left;">Page</th>
		<td>{{ $data['page'] }}</td>
	</tr>
</table>
<table class="table" border="1" width="100%" style="border-collapse: collapse;">
	<tr>
		<th width="20%" style="text-align: center; padding: 2px;">ORDER ID</th>
		<th width="20%" style="text-align: center; padding: 2px;">ORDER DATE</th>
		<th width="10%" style="text-align: center; padding: 2px;">FEE</th>
		<th width="50%" style="text-align: left; padding: 2px;">MESSAGE</th>
	</tr>
	@foreach($data['rows'] as $row)
		@if(in_array($row['status'], ['error', 'success']))
		<tr style="
			@if($row['status'] == 'error')
				background-color: #B22222; color:#fff; font-weight: bold;
			@elseif($row['status'] == 'success' && $row['fee'] != 0)
				background-color: #32CD32; color:#fff; font-weight: bold;
			@endif
		">
			<td style="text-align: center; padding: 2px;">{{ $row['amazon_order_id'] }}</td>
			<td style="text-align: center; padding: 2px; text-transform: uppercase;">{{ $row['order_date'] }}</td>
			<td style="text-align: center; padding: 2px; text-transform: uppercase;">{{ $row['fee'] }}</td>
			<td style="text-align: left; padding: 2px;">{{ $row['message'] }}</td>
		</tr>
		@endif
	@endforeach
</table>