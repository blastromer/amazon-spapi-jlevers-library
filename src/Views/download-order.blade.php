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
		<th style="text-align: left;">Records From</th>
		<td>{{ $data['created_after'] }}</td>
	</tr>
	<tr>
		<th style="text-align: left;">Records To</th>
		<td>{{ $data['created_before'] }}</td>
	</tr>
	<tr>
		<th style="text-align: left;">Fulfillment Channels</th>
		<td>{{ $data['fulfillment_channels'] }}</td>
	</tr>
</table>
<table class="table" border="1" width="100%" style="border-collapse: collapse;">
	<tr>
		<th width="20%" style="text-align: center; padding: 2px;">ORDER ID</th>
		<th width="15%" style="text-align: center; padding: 2px;">STATUS</th>
		<th width="65%" style="text-align: left; padding: 2px;">MESSAGE</th>
	</tr>
	@foreach($data['rows'] as $row)
		@if($row['message'] != 'Exist')
		<tr style="
			@if($row['status'] == 'error')
				background-color: #B22222; color:#fff; font-weight: bold;
			@elseif($row['status'] == 'success' && $row['message'] != 'Exist')
				background-color: #32CD32; color:#fff; font-weight: bold;
			@endif
		">
			<td style="text-align: center; padding: 2px;">{{ $row['amazon_order_id'] }}</td>
			<td style="text-align: center; padding: 2px; text-transform: uppercase;">{{ $row['status'] }}</td>
			<td style="text-align: left; padding: 2px;">{{ $row['message'] }}</td>
		</tr>
		@endif
	@endforeach
</table>