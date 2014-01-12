<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
   xmlns:php="http://php.net/xsl" xsl:extension-element-prefixes="php">

<!--
 Styleseet to transform MARCXML/OPAC-syntax records returned by opac.php script (which uses yaz/php) into a results list. 
 Accompanying files: opac.php, opac.js, opac.css. Last modified 2007-01-06 mjordan@sfu.ca. 
-->

 <!--
 opac.php passes 'start_list_at' parameter which is picked up here. It is used in this stylesheet only to
 set the 'start' attribute of the <ol> tag, and to adjust the value of 'position()' appropriately
 (position adjustment not done yet).
 -->
 <xsl:param name="start_list_at">1</xsl:param>

 <!-- 'result_set' and 'result_count' elements are added by opac.php and are not part of MARCXML -->
 <xsl:template match="result_set">
  <div class="num_recs">Number of records found: <xsl:value-of select="result_count"/></div>
  <ol start="{$start_list_at}">
   <xsl:for-each select="opacRecord">
      <!-- We only want MARC 245 fields in the initial results display -->
      <xsl:if test="bibliographicRecord/record/datafield[@tag='245']"> 
         <li class="item_entry"><a href="#" title="Click to show/hide record details" onclick="toggle_vis({position()});"><xsl:value-of select="bibliographicRecord/record/datafield[@tag='245']"/></a></li>
      </xsl:if>

   <!-- MARC fields and copy status information in the expanded record details -->
   <div class="outer_wrapper" id="{position()}" style="display: none;"> <!-- Used for two colum layout -->
   <div class="record_details">
   <table>
   <xsl:for-each select="bibliographicRecord/record/datafield[@tag='245']">
       <tr valign="top"><td class="title_label">Title</td><td><xsl:value-of select="."/></td></tr>
   </xsl:for-each>

   <xsl:for-each select="bibliographicRecord/record/datafield[@tag='100']">
        <tr valign="top"><td class="subject_label">Author</td>
	<td><a title="Click to search more"><xsl:attribute name="href">
        <xsl:value-of select="php:function('opac_format_heading_links', '1003', string(.))"/></xsl:attribute>
        <xsl:value-of select="."/>
        </a></td></tr>
   </xsl:for-each>

   <xsl:for-each select="bibliographicRecord/record/datafield[@tag='260']">
       <tr valign="top"><td class="publisher_label">Publisher</td><td><xsl:value-of select="."/></td></tr>
   </xsl:for-each>

   <xsl:for-each select="bibliographicRecord/record/datafield[@tag='650']">
        <tr valign="top"><td class="subject_label">Subject</td>
	<td><a title="Click to search more"><xsl:attribute name="href"><xsl:value-of select="php:function('opac_format_heading_links', '21', string(.))"/></xsl:attribute>
        <xsl:value-of select="."/>
        </a></td></tr>
   </xsl:for-each>

   <xsl:for-each select="bibliographicRecord/record/datafield[@tag='020']">
       <tr valign="top"><td class="isbn_label">ISBN</td><td><xsl:value-of select="."/></td></tr>
   </xsl:for-each>

   <xsl:for-each select="bibliographicRecord/record/datafield[@tag='022']">
       <tr valign="top"><td class="issn_label">ISSN</td><td><xsl:value-of select="."/></td></tr>
   </xsl:for-each>

   <xsl:for-each select="bibliographicRecord/record/datafield[@tag='856']">
        <tr valign="top"><td class="url_label">URL</td><td>
	<a><xsl:attribute name="href"><xsl:value-of select="subfield[@code='u']"/></xsl:attribute>Available online</a>
	</td></tr>
   </xsl:for-each>

   <tr valign="top"><td class="other_links_label">Outside links</td><td>

   <!-- Get title for linking out, but only use part before the first '/', since the title of repsonsibility makes 
        searches in external engines too narrow. --> 
   <xsl:variable name="title_as_url" select="substring-before(normalize-space(bibliographicRecord/record/datafield[@tag='245']), '/')"/> 
   [<a href="http://www.searchmash.com/search/{$title_as_url}">Google searchmash</a>]  

    <!-- Get and format ISBNs for use in amazon.com links. Many MARC 020 fields contain text other than ISBNs,
	 so we need to strip off an additional text. Assumption: this extraneous text follows the ISBN and
	 is separated from it by a ' '. XSL's substring-before() doesn't work if the string doesn't contain the
	 matching string (' ' in this case), so we need to use xsl:choose to distinguish between the cases where
	 the 020 contains only an ISBN and those where the ISBN is followed by a space and text we want to remove. --> 

    <xsl:if test="bibliographicRecord/record/datafield[@tag='020']">
      <xsl:choose>
	<xsl:when test="contains(bibliographicRecord/record/datafield[@tag='020'], ' ')"> 
            <xsl:variable name="isbn_as_url" select="php:function('opac_clean_isbn', normalize-space(bibliographicRecord/record/datafield[@tag='020']))"/>
             [<a href="http://www.amazon.com/dp/{$isbn_as_url}">Amazon.com</a>]
             <tr valign="top"><td>Cover courtesy<br />Amazon.com<br /><span style="font-size: 60%">(if available)</span></td><td><img src="http://images.amazon.com/images/P/{$isbn_as_url}.01.THUMBZZZ.jpg" /></td></tr>
	</xsl:when>
	<xsl:otherwise> 
           <xsl:variable name="isbn_as_url" select="normalize-space(bibliographicRecord/record/datafield[@tag='020'])"/>
             [<a href="http://www.amazon.com/dp/{$isbn_as_url}">Amazon.com</a>]
             <tr valign="top"><td>Cover courtesy<br />Amazon.com<br /><span style="font-size: 60%">(if available)</span></td><td><img src="http://images.amazon.com/images/P/{$isbn_as_url}.01.THUMBZZZ.jpg" /></td></tr>
	</xsl:otherwise> 
      </xsl:choose>
    </xsl:if>
   </td></tr>

    <xsl:if test="bibliographicRecord/record/datafield[@tag='505']">
    <tr valign="top"><td><a title="Click to show/hide contents" href="#" onclick="toggle_vis({position() * 1000});">Contents</a></td><td>
        <!-- Generate a numeric ID by multiplying position() * 1000 -->
	<div class="toc" id="{position() * 1000}" style="display: none;">
        <!-- <xsl:for-each select="datafield[@tag='505']"> -->
	<!-- disable-output-escaping="yes" is necessary so that we don't get &lt;, &gt;, etc back from opac_format_toc -->
	<xsl:value-of select="php:function('opac_format_toc', string(bibliographicRecord/record/datafield[@tag='505']))" disable-output-escaping="yes"/>
        <!-- </xsl:for-each> -->
	</div>
     </td></tr>
   </xsl:if>
   </table>
   </div> <!-- record_details -->

   <div class="holdings">
   <xsl:for-each select="holdings/holding">
       <div class="holding">
       <table>
       <tr valign="top"><td class="holdings_label">Local location</td><td><xsl:value-of select="localLocation"/></td></tr> 
       <tr valign="top"><td class="holdings_label">Call number</td><td><xsl:value-of select="callNumber"/></td></tr> 
       <tr valign="top"><td class="holdings_label">Status</td><td><xsl:value-of select="publicNote"/></td></tr> 
       </table>
       </div>
   </xsl:for-each>
   </div> <!-- holdings -->

   </div> <!-- outer_wrapper -->
   </xsl:for-each> <!-- select="opacRecord" -->
  </ol>
 </xsl:template>
</xsl:stylesheet>
