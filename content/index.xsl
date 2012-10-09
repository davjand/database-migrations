<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:template match="/">
		<div>
			<h2>Note - Clicking any of the following could put your health at risk.</h2>
			<div style="padding-left:25px;">
				<a href="?action=cleaninstall">Clean Install</a> - Completely refresh the database with a clean install. <br/><br/>
				<a href="?action=update">Manual Update</a> - Update the database using transaction files. <br/><br/>
				<a href="?action=baseline">Create Baseline</a> - Create a new baseline, and remove all old transaction files. <br/><br/>
			</div>
		</div>
	</xsl:template>	
		
</xsl:stylesheet>