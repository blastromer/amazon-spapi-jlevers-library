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
</table>
<table width="40%" border="1" style="border-collapse: collapse;">
	<tr>
		<th width="20%" style="text-align: center; padding: 2px;">ORDER ID</th>
		<th width="20%" style="text-align: center; padding: 2px;">Order Date</th>
	</tr>
	@foreach($data['rows'] as $row)
	<tr style="">
		<td style="text-align: center; padding: 2px;">{{ $row->OrderId }}</td>
		<td style="text-align: center; padding: 2px;">{{ date('Y-M-d H:i', strtotime($row->OrderDate)) }}</td>
	</tr>
	@endforeach
</table>