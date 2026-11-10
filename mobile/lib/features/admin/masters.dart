import 'package:flutter/material.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';
import '../../widgets/manager_ui.dart';

class CategoryMasterScreen extends StatefulWidget {
  final Auth auth;
  const CategoryMasterScreen({super.key, required this.auth});
  @override
  State<CategoryMasterScreen> createState() => _CategoryMasterState();
}

class _CategoryMasterState extends State<CategoryMasterScreen> {
  String search = '';
  Auth get auth => widget.auth;
  @override
  Widget build(BuildContext context) => RemotePage(
    auth: auth,
    title: 'Category Master',
    route: '/categories',
    content: (ctx, r, reload) => Column(
      children: [
        if (auth.user?['role'] == 'admin')
          PrimaryButton(
            '+ Add Category',
            onPressed: () async {
              await openPage(ctx, CategoryEditScreen(auth: auth));
              reload();
            },
          ),
        const SizedBox(height: 12),
        TextField(
          decoration: field('Search categories', icon: Icons.search),
          onChanged: (v) => setState(() => search = v.toLowerCase()),
        ),
        const SizedBox(height: 12),
        for (final c in (r['categories'] as List).where(
          (c) => '${c['category_name']} ${c['aliases']}'.toLowerCase().contains(
            search,
          ),
        ))
          Card(
            child: ListTile(
              leading: const Icon(Icons.folder_outlined, color: Colors.orange),
              title: Text(c['category_name']),
              subtitle: Text(
                '${number(c['active']) == 1 ? 'Active' : 'Inactive'} · Order ${c['display_order']}\n${c['aliases']}',
              ),
              isThreeLine: true,
              trailing: const Icon(Icons.chevron_right),
              onTap: auth.user?['role'] == 'admin'
                  ? () async {
                      await openPage(
                        ctx,
                        CategoryEditScreen(auth: auth, category: c),
                      );
                      reload();
                    }
                  : null,
            ),
          ),
      ],
    ),
  );
}

class CategoryEditScreen extends StatefulWidget {
  final Auth auth;
  final Map? category;
  const CategoryEditScreen({super.key, required this.auth, this.category});
  @override
  State<CategoryEditScreen> createState() => _CategoryEditState();
}

class _CategoryEditState extends State<CategoryEditScreen> {
  late final name = TextEditingController(
        text: widget.category?['category_name'],
      ),
      slug = TextEditingController(text: widget.category?['category_slug']),
      aliases = TextEditingController(text: widget.category?['aliases']),
      order = TextEditingController(
        text: '${widget.category?['display_order'] ?? 0}',
      );
  late bool active = number(widget.category?['active'] ?? 1) == 1;
  bool busy = false;
  String? error;
  @override
  void dispose() {
    for (final c in [name, slug, aliases, order]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> save() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.auth.api.request(
        '/categories',
        data: {
          if (widget.category != null) 'id': number(widget.category!['id']),
          'category_name': name.text,
          'category_slug': slug.text,
          'aliases': aliases.text,
          'display_order': number(order.text),
          'active': active,
        },
      );
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(widget.category == null ? 'Add Category' : 'Edit Category'),
    ),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          if (error != null) Notice(error!),
          for (final (controller, label) in [
            (name, 'Category Name'),
            (slug, 'URL Slug'),
            (aliases, 'Aliases (comma separated)'),
            (order, 'Display Order'),
          ])
            Padding(
              padding: const EdgeInsets.only(bottom: 20),
              child: TextField(
                controller: controller,
                decoration: field(label),
              ),
            ),
          SwitchListTile(
            title: const Text('Active'),
            value: active,
            onChanged: (v) => setState(() => active = v),
          ),
          PrimaryButton('Save Changes', busy: busy, onPressed: save),
          if (widget.category != null)
            TextButton(
              onPressed: () async {
                if (!await confirmAction(
                  context,
                  'Delete category?',
                  'Referenced categories must be deactivated instead to preserve history.',
                )) {
                  return;
                }
                try {
                  await widget.auth.api.request(
                    '/categories/${widget.category!['id']}/delete',
                    data: {},
                  );
                  if (context.mounted) Navigator.pop(context);
                } catch (e) {
                  if (mounted) setState(() => error = e.toString());
                }
              },
              child: const Text(
                'Delete Category',
                style: TextStyle(color: Colors.red),
              ),
            ),
        ],
      ),
    ),
  );
}

class EmployeesScreen extends StatefulWidget {
  final Auth auth;
  const EmployeesScreen({super.key, required this.auth});
  @override
  State<EmployeesScreen> createState() => _EmployeesState();
}

class _EmployeesState extends State<EmployeesScreen> {
  String search = '';
  Auth get auth => widget.auth;
  @override
  Widget build(BuildContext context) => RemotePage(
    auth: auth,
    title: 'Employees',
    route: '/employees',
    content: (ctx, r, reload) => Column(
      children: [
        PrimaryButton(
          '+ Add Employee',
          onPressed: () async {
            await openPage(ctx, EmployeeEditScreen(auth: auth));
            reload();
          },
        ),
        const SizedBox(height: 14),
        TextField(
          decoration: field('Search employees', icon: Icons.search),
          onChanged: (v) => setState(() => search = v.toLowerCase()),
        ),
        const SizedBox(height: 12),
        for (final u in (r['employees'] as List).where(
          (u) => '${u['name']} ${u['username']}'.toLowerCase().contains(search),
        ))
          Card(
            child: ListTile(
              leading: CircleAvatar(
                child: Text('${u['name']}'.substring(0, 1)),
              ),
              title: Text(u['name']),
              subtitle: Text('${u['role']} · ${u['status']}'),
              trailing: const Icon(Icons.chevron_right),
              onTap: u['role'] == 'employee'
                  ? () async {
                      await openPage(
                        ctx,
                        EmployeeEditScreen(auth: auth, employee: u),
                      );
                      reload();
                    }
                  : null,
            ),
          ),
      ],
    ),
  );
}

class EmployeeEditScreen extends StatefulWidget {
  final Auth auth;
  final Map? employee;
  const EmployeeEditScreen({super.key, required this.auth, this.employee});
  @override
  State<EmployeeEditScreen> createState() => _EmployeeEditState();
}

class _EmployeeEditState extends State<EmployeeEditScreen> {
  late final fields = {
    for (final k in [
      'username',
      'first_name',
      'last_name',
      'email',
      'contact_no',
      'address',
    ])
      k: TextEditingController(text: widget.employee?[k] ?? ''),
  };
  final password = TextEditingController();
  late Set<String> permissions = Set<String>.from(
    widget.employee?['permissions'] ?? ['view'],
  );
  late bool active = widget.employee?['status'] != 'inactive';
  bool busy = false;
  String? error;
  @override
  void dispose() {
    for (final c in fields.values) {
      c.dispose();
    }
    password.dispose();
    super.dispose();
  }

  Future<void> save() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.auth.api.request(
        '/employees',
        data: {
          if (widget.employee != null) 'id': number(widget.employee!['id']),
          for (final e in fields.entries) e.key: e.value.text,
          'password': password.text,
          'permissions': permissions.toList(),
          'status': active ? 'active' : 'inactive',
        },
      );
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        widget.employee == null ? 'Add Employee' : 'Employee & Permissions',
      ),
    ),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          if (error != null) Notice(error!),
          for (final e in fields.entries)
            Padding(
              padding: const EdgeInsets.only(bottom: 17),
              child: TextField(
                controller: e.value,
                decoration: field(
                  {
                    'username': 'Username',
                    'first_name': 'First Name',
                    'last_name': 'Last Name',
                    'email': 'Registered Email',
                    'contact_no': 'Contact Number',
                    'address': 'Address',
                  }[e.key]!,
                ),
              ),
            ),
          TextField(
            controller: password,
            obscureText: true,
            decoration: field(
              widget.employee == null
                  ? 'Password (10+ characters)'
                  : 'New Password (leave blank to keep)',
            ),
          ),
          SwitchListTile(
            title: const Text('Active Account'),
            value: active,
            onChanged: (v) => setState(() => active = v),
          ),
          const Text(
            'Permissions',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 20),
          ),
          for (final p in ['view', 'create', 'edit', 'delete', 'download'])
            SwitchListTile(
              title: Text('${p[0].toUpperCase()}${p.substring(1)} newsletters'),
              value: permissions.contains(p),
              onChanged: (v) => setState(() {
                if (v) {
                  permissions.add(p);
                } else {
                  permissions.remove(p);
                }
              }),
            ),
          const SizedBox(height: 20),
          PrimaryButton(
            'Save Employee & Permissions',
            busy: busy,
            onPressed: save,
          ),
        ],
      ),
    ),
  );
}
