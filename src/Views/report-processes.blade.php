<table class="table" border="1" width="100%" style="border-collapse: collapse;">
	<tr>
		<th width="30%" style="text-align: left; padding: 2px;">Process</th>
		<th width="30%" style="text-align: left; padding: 2px;">Name</th>
		<th width="20%" style="text-align: center; padding: 2px;">Daily Run</th>
		<th width="20%" style="text-align: center; padding: 2px;">Total Run</th>
	</tr>
	@foreach($data['rows'] as $row)
	<tr>
		<td style="text-align: left; padding: 2px;">{{ $row->signature }}</td>
		<td style="text-align: left; padding: 2px;">{{ $row->name }}</td>
		<td style="text-align: center; padding: 2px;">{{ $row->daily_run }}</td>
		<td style="text-align: center; padding: 2px;">{{ $row->total_run }}</td>
	</tr>
	@endforeach
</table>