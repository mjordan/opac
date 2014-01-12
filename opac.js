/*
JavaScript library accompanying opac.php. Last modified 2006-11-21 mjordan@sfu.ca.
*/

/*
Toggles visibility of the '+/-' when in browse mode
*/
function toggle_add_clear_boolean() {
   if (document.query_form.opac_function.value == 'Find') {
      document.getElementById('add_clear_keywords').style.display = "inline"; 
   }
   if (document.query_form.opac_function.value == 'Browse') {
      document.getElementById('add_clear_keywords').style.display = "none"; 
      // We want to hide the keywords2 form element, etc. when in browse mode
      document.getElementById('query2').style.display = "none"; 
   }
}

/*
Deletes the value of the keywords2 form element and resets boolean_op to 'and'
*/
function clear_keywords2() {
   document.query_form.keywords2.value = '';
   document.query_form.boolean_op.value = 'and';
   // 4 = title in z39.50 speak
   document.query_form.field2.value = '4'; 
}

/*
Toggles visibility of divs
*/
function toggle_vis(id) {
   if (document.getElementById(id).style.display == "none") {
      document.getElementById(id).style.display = "block";
   } else {
      document.getElementById(id).style.display = "none";
   }
}


