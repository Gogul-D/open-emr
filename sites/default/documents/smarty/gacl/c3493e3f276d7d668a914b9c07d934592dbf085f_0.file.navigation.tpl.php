<?php
/* Smarty version 4.3.4, created on 2025-02-11 14:28:54
  from '/var/www/openemr/gacl/admin/templates/phpgacl/navigation.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_67abc116120603_27877669',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'c3493e3f276d7d668a914b9c07d934592dbf085f' => 
    array (
      0 => '/var/www/openemr/gacl/admin/templates/phpgacl/navigation.tpl',
      1 => 1700108884,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_67abc116120603_27877669 (Smarty_Internal_Template $_smarty_tpl) {
?>		<div id="top-tr"><div id="top-tl"><div id="top-br"><div id="top-bl">
			<h1><span>phpGACL</span></h1>
			<h2><?php echo text($_smarty_tpl->tpl_vars['page_title']->value);?>
</h2>
<?php if (!(isset($_smarty_tpl->tpl_vars['hidemenu']->value)) || $_smarty_tpl->tpl_vars['hidemenu']->value != TRUE) {?>
            <p><a href='../../interface/usergroup/adminacl.php' onclick='top.restoreSession()'><span style='font-size: 80%;'>(Back to OpenEMR's ACL menu)</span></a></p>
			<ul id="menu">
				<li<?php if ($_smarty_tpl->tpl_vars['current']->value == 'aro_group') {?> class="current"<?php }?>><a href="group_admin.php?group_type=aro">ARO Group Admin</a></li>
				<li<?php if ($_smarty_tpl->tpl_vars['current']->value == 'axo_group') {?> class="current"<?php }?>><a href="group_admin.php?group_type=axo">AXO Group Admin</a></li>
				<li<?php if ($_smarty_tpl->tpl_vars['current']->value == 'acl_admin') {?> class="current"<?php }?>><a href="acl_admin.php?return_page=acl_admin.php">ACL Admin</a></li>
				<li<?php if ($_smarty_tpl->tpl_vars['current']->value == 'acl_list') {?> class="current"<?php }?>><a href="acl_list.php?return_page=acl_list.php">ACL List</a></li>
				<li<?php if ($_smarty_tpl->tpl_vars['current']->value == 'acl_test') {?> class="current"<?php }?>><a href="acl_test.php">ACL Test</a></li>
				<li<?php if ($_smarty_tpl->tpl_vars['current']->value == 'acl_debug') {?> class="current"<?php }?>><a href="acl_debug.php">ACL Debug</a></li>
				<li<?php if ($_smarty_tpl->tpl_vars['current']->value == 'about') {?> class="current"<?php }?>><a href="about.php">About</a></li>
				<li><a href="../docs/manual.html" rel="noopener" target="_blank">Manual</a></li>
				<li><a href="../docs/phpdoc/" rel="noopener" target="_blank">API Guide</a></li>
			</ul>
<?php }?>
		</div></div></div></div>

		<div id="mid-r"><div id="mid-l">
<?php }
}
