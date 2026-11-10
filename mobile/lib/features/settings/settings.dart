import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';
import '../../widgets/manager_ui.dart';
import '../admin/masters.dart';
import '../automation/monitor.dart';
import '../newsletters/library.dart';
import '../notifications/notifications.dart';

class SettingsScreen extends StatelessWidget {
  final Auth auth;
  const SettingsScreen({super.key, required this.auth});
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('More & Settings')),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Card(
            child: ListTile(
              leading: const Brand(size: 48),
              title: Text(
                auth.user?['name'] ?? 'TTW Account',
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              subtitle: Text('${auth.user?['role'] ?? ''}'),
            ),
          ),
          _link(
            context,
            'Profile',
            Icons.person_outline,
            ProfileScreen(auth: auth),
          ),
          if (auth.can('view')) ...[
            _link(
              context,
              'Newsletter History',
              Icons.history,
              LibraryScreen(auth: auth, history: true),
            ),
            _link(
              context,
              'Category Master',
              Icons.folder_outlined,
              CategoryMasterScreen(auth: auth),
            ),
          ],
          if (auth.can('create'))
            _link(
              context,
              'Automation',
              Icons.auto_mode,
              AutomationScreen(auth: auth),
            ),
          if (auth.user?['role'] == 'admin')
            _link(
              context,
              'Employees & Permissions',
              Icons.people_outline,
              EmployeesScreen(auth: auth),
            ),
          _link(
            context,
            'Notifications',
            Icons.notifications_outlined,
            NotificationsScreen(auth: auth),
          ),
          _link(
            context,
            'API / Server Status',
            Icons.dns_outlined,
            RemotePage(
              auth: auth,
              title: 'Server Status',
              route: '/system/status',
              content: (ctx, r, _) => Column(
                children: [
                  StatusBadge(r['online'] == true ? 'Active' : 'Failed'),
                  ListTile(
                    title: const Text('Server time'),
                    subtitle: Text('${r['server_time']}'),
                  ),
                  ListTile(
                    title: const Text('HTML publishing'),
                    subtitle: Text(
                      r['publishing_configured'] == true ? 'Configured' : 'Ask your administrator to configure the main website API.',
                    ),
                  ),
                  ListTile(
                    title: const Text('Password reset email'),
                    subtitle: Text(
                      r['reset_email_configured'] == true ? 'Configured' : 'Requires mail configuration and a registered account email.',
                    ),
                  ),
                ],
              ),
            ),
          ),
          _link(
            context,
            'Change Password',
            Icons.lock_outline,
            PasswordScreen(auth: auth),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.help_outline, color: blue),
              title: const Text('Help & Support'),
              onTap: () => showDialog<void>(
                context: context,
                builder: (ctx) => AlertDialog(
                  title: const Text('TTW Newsletter Manager'),
                  content: const Text(
                    'Create a draft from All Links or Section by Section. Save each section, review extracted content, add Email Title, approve, then Publish HTML. If jobs stay pending, ask the administrator to check the existing cron. Publishing requires the separate main website upload API.',
                  ),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.pop(ctx),
                      child: const Text('Close'),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.info_outline, color: blue),
              title: const Text('About App'),
              trailing: const Text('v0.3.0'),
              onTap: () => showAboutDialog(
                context: context,
                applicationName: 'TTW Newsletter Manager',
                applicationVersion: '0.3.0',
                applicationIcon: const Brand(size: 60),
                children: [
                  const Text(
                    'Travel and Tour World newsletter creation, management and HTML publishing. Uses your existing website account and newsletter data.',
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
          OutlinedButton.icon(
            onPressed: () async {
              if (!await confirmAction(
                context,
                'Logout?',
                'Sign out of this device?',
              )) {
                return;
              }
              try {
                await auth.logout();
              } catch (e) {
                if (context.mounted) showFailure(context, e);
              }
            },
            icon: const Icon(Icons.logout, color: Colors.red),
            label: const Text('Logout', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    ),
  );
  Widget _link(BuildContext ctx, String title, IconData icon, Widget screen) =>
      Card(
        child: ListTile(
          leading: Icon(icon, color: blue),
          title: Text(title),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => openPage(ctx, screen),
        ),
      );
}

class ProfileScreen extends StatefulWidget {
  final Auth auth;
  const ProfileScreen({super.key, required this.auth});
  @override
  State<ProfileScreen> createState() => _ProfileState();
}

class _ProfileState extends State<ProfileScreen> {
  final fields = {
    for (final k in [
      'first_name',
      'last_name',
      'email',
      'contact_no',
      'address',
      'current_password',
    ])
      k: TextEditingController(),
  };
  bool busy = true;
  String? error, photo;
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    for (final c in fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> load() async {
    try {
      final r = await widget.auth.api.request('/profile');
      final u = r['user'] as Map;
      for (final e in fields.entries) {
        e.value.text = u[e.key] ?? '';
      }
      if (mounted) setState(() => photo = u['photo']);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> save() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final r = await widget.auth.api.request(
        '/profile',
        data: {for (final e in fields.entries) e.key: e.value.text},
      );
      widget.auth.updateUser(r['user']);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Profile saved.')));
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> changePhoto() async {
    final image = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1000,
    );
    if (image == null) return;
    setState(() => busy = true);
    try {
      final r = await widget.auth.api.upload(
        image.path,
        route: '/profile/photo',
        fileKey: 'photo',
      );
      if (mounted) setState(() => photo = r['photo']);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Profile')),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          if (busy) const LinearProgressIndicator(),
          if (error != null) Notice(error!),
          Center(
            child: CircleAvatar(
              radius: 45,
              backgroundImage: photo != null
                  ? NetworkImage(
                      widget.auth.api.imageUrl(photo!, kind: 'profile'),
                    )
                  : null,
              child: photo == null ? const Icon(Icons.person, size: 40) : null,
            ),
          ),
          TextButton(
            onPressed: busy ? null : changePhoto,
            child: const Text('Change Photo'),
          ),
          for (final e in fields.entries)
            Padding(
              padding: const EdgeInsets.only(bottom: 18),
              child: TextField(
                controller: e.value,
                obscureText: e.key == 'current_password',
                decoration: field(
                  {
                    'first_name': 'First Name',
                    'last_name': 'Last Name',
                    'email': 'Registered Email',
                    'contact_no': 'Contact Number',
                    'address': 'Address',
                    'current_password':
                        'Current Password (only to change email)',
                  }[e.key]!,
                ),
              ),
            ),
          PrimaryButton('Save Profile', busy: busy, onPressed: save),
        ],
      ),
    ),
  );
}

class PasswordScreen extends StatefulWidget {
  final Auth auth;
  const PasswordScreen({super.key, required this.auth});
  @override
  State<PasswordScreen> createState() => _PasswordState();
}

class _PasswordState extends State<PasswordScreen> {
  final old = TextEditingController(),
      password = TextEditingController(),
      confirm = TextEditingController();
  bool busy = false;
  String? error;
  @override
  void dispose() {
    old.dispose();
    password.dispose();
    confirm.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (password.text != confirm.text) {
      setState(() => error = 'Passwords do not match.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.auth.api.request(
        '/auth/password',
        data: {'current_password': old.text, 'password': password.text},
      );
      widget.auth.expire();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Change Password')),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const Text('Changing your password signs out all devices.'),
          const SizedBox(height: 24),
          for (final (c, label) in [
            (old, 'Current Password'),
            (password, 'New Password (10+ characters)'),
            (confirm, 'Confirm Password'),
          ])
            Padding(
              padding: const EdgeInsets.only(bottom: 20),
              child: TextField(
                controller: c,
                obscureText: true,
                decoration: field(label),
              ),
            ),
          if (error != null) Notice(error!),
          PrimaryButton('Change Password', busy: busy, onPressed: save),
        ],
      ),
    ),
  );
}
