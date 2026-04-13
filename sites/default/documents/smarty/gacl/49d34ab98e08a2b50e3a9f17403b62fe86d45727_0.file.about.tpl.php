<?php
/* Smarty version 4.3.4, created on 2025-04-21 11:38:37
  from '/var/www/openemr_tb/gacl/admin/templates/phpgacl/about.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_6806829db95367_76541300',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '49d34ab98e08a2b50e3a9f17403b62fe86d45727' => 
    array (
      0 => '/var/www/openemr_tb/gacl/admin/templates/phpgacl/about.tpl',
      1 => 1700108884,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:phpgacl/header.tpl' => 1,
    'file:phpgacl/navigation.tpl' => 1,
    'file:phpgacl/footer.tpl' => 1,
  ),
),false)) {
function content_6806829db95367_76541300 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_subTemplateRender("file:phpgacl/header.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
  </head>
  <body>
	<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/navigation.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
?>
    <div style="text-align: center;">
      <table cellpadding="2" cellspacing="2" border="2" align="center">
        <tbody>
          <tr>
			<th>
				Report
			</th>
          </tr>
          <tr>
			<td align="center">
				<textarea name="system_information" rows="10" cols="60" wrap="VIRTUAL"><?php echo text($_smarty_tpl->tpl_vars['system_info']->value);?>
</textarea>
			</td>
          </tr>
		  <tr>
			<th>
				Credits
			</th>
          </tr>
          <tr>
			<td>
<pre>
<?php echo text($_smarty_tpl->tpl_vars['credits']->value);?>

</pre>
			</td>
          </tr>
        </tbody>
      </table>
    </div>
<?php $_smarty_tpl->_subTemplateRender("file:phpgacl/footer.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
}
}
