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
		<th style="text-align: left;">Success</th>
		<td>{{ $data['success'] }}</td>
	</tr>
	<tr>
		<th style="text-align: left;">Fail</th>
		<td>{{ $data['fail'] }}</td>
	</tr>
</table>