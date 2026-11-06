import 'package:flutter/material.dart';

import '../../core/auth.dart';
import '../../models/newsletter.dart';
import '../../widgets/ui.dart';
import '../create_newsletter/create.dart';
import '../automation/job.dart';
import '../newsletter_preview/preview.dart';

class HomeScreen extends StatefulWidget {
  final Auth auth;
  const HomeScreen({super.key, required this.auth});
  @override
  State<HomeScreen> createState() => _HomeState();
}

class _HomeState extends State<HomeScreen> {
  int tab = 0, historyPage = 1;
  Map<String, dynamic>? data;
  List<dynamic> jobs = [], categories = [];
  List<Newsletter> history = [];
  bool busy = false, more = true;
  String? error;
  Auth get auth => widget.auth;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final results = await Future.wait([
        if (auth.can('view')) auth.api.request('/newsletters'),
        if (auth.can('create')) auth.api.request('/automation/jobs'),
        if (auth.can('view')) auth.api.request('/categories'),
      ]);
      if (!mounted) return;
      var i = 0;
      setState(() {
        if (auth.can('view')) {
          data = results[i++];
          history = (data!['newsletters'] as List)
              .map((r) => Newsletter.fromJson(r))
              .toList();
          historyPage = 1;
          more = history.length == 30;
        }
        if (auth.can('create')) jobs = results[i++]['jobs'] as List;
        if (auth.can('view')) categories = results[i++]['categories'] as List;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> open(Widget screen) async {
    await Navigator.of(context)
        .push(MaterialPageRoute<void>(builder: (_) => screen));
    if (mounted) load();
  }

  Future<void> loadMore() async {
    try {
      final r = await auth.api.request(
        '/newsletters',
        query: {'page': '${historyPage + 1}'},
      );
      if (mounted) {
        setState(() {
          final rows = (r['newsletters'] as List)
              .map((r) => Newsletter.fromJson(r))
              .toList();
          history.addAll(rows);
          historyPage++;
          more = rows.length == 30;
        });
      }
    } catch (e) {
      if (mounted) showFailure(context, e);
    }
  }

  Widget newsletterTile(Newsletter n) => Card(
    child: ListTile(
      leading: const Icon(Icons.calendar_month_outlined, color: navy),
      title: Text(
        '${n.date} · ${n.edition == 'M' ? 'Morning' : 'Evening'}',
        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
      ),
      subtitle: Text(
        '${n.subject}\n${n.approved ? '✓ Approved' : 'Draft'}',
        maxLines: 3,
      ),
      trailing: const Icon(Icons.visibility_outlined),
      onTap: () => open(PreviewScreen(auth: auth, id: n.id)),
    ),
  );
  Widget edition(String e) {
    final today = (data?['today'] as List? ?? [])
        .where((n) => n['edition'] == e)
        .toList();
    final status = today.isEmpty
        ? '○ Not created'
        : today.first['approved_at'] != null
        ? '✓ Approved'
        : '✓ Draft created';
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(18),
          gradient: LinearGradient(
            colors: e == 'M'
                ? [const Color(0xff15b8df), const Color(0xff4936ef)]
                : [const Color(0xffe359c0), red],
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              e == 'M' ? Icons.wb_sunny_outlined : Icons.nightlight_outlined,
              color: Colors.white,
            ),
            const SizedBox(height: 12),
            Text(
              '${e == 'M' ? 'Morning' : 'Evening'}\nEdition',
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.bold,
                fontSize: 18,
              ),
            ),
            const SizedBox(height: 12),
            Text(
              status,
              style: const TextStyle(color: Colors.white, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }

  Widget dashboard() => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        width: double.infinity,
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(22),
          gradient: const LinearGradient(
            colors: [Color(0xffe2ebff), Colors.white],
          ),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Good ${DateTime.now().hour < 12 ? 'Morning' : 'Afternoon'},\n${auth.user?['name']} 👋',
                    style: const TextStyle(
                      fontSize: 23,
                      fontWeight: FontWeight.w800,
                      color: navy,
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Manage your daily newsletters\nand keep your audience updated.',
                  ),
                ],
              ),
            ),
            const Brand(size: 90),
          ],
        ),
      ),
      const SizedBox(height: 20),
      Row(children: [edition('M'), const SizedBox(width: 14), edition('E')]),
      const SizedBox(height: 20),
      if (auth.can('create'))
        PrimaryButton(
          '+  Create Newsletter',
          onPressed: () => open(CreateScreen(auth: auth)),
        ),
      const SizedBox(height: 18),
      const Text(
        'Recent Newsletters',
        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
      ),
      if (history.isEmpty)
        const Padding(
          padding: EdgeInsets.all(16),
          child: Text('No newsletters yet.'),
        ),
      ...history.take(5).map(newsletterTile),
      const SizedBox(height: 18),
      const Text(
        'Automation Jobs',
        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
      ),
      ...jobs.map(
        (j) => Card(
          child: ListTile(
            leading: Icon(
              j['status'] == 'failed'
                  ? Icons.warning_amber
                  : Icons.auto_awesome_motion,
              color: navy,
            ),
            title: Text(
              'Job #${j['id']} · ${(j['status'] as String).replaceAll('_', ' ')}',
            ),
            subtitle: Text('${j['completed']}/${j['total']} articles'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () =>
                open(JobScreen(auth: auth, id: int.parse('${j['id']}'))),
          ),
        ),
      ),
      if (jobs.isEmpty)
        const Padding(
          padding: EdgeInsets.all(16),
          child: Text('Your processing jobs will appear here.'),
        ),
    ],
  );
  Future<void> editCategory([Map<String, dynamic>? c]) async {
    final name = TextEditingController(text: c?['category_name']);
    final slug = TextEditingController(text: c?['category_slug']);
    final aliases = TextEditingController(text: c?['aliases']);
    final order = TextEditingController(text: '${c?['display_order'] ?? 0}');
    bool active = c == null || int.parse('${c['active']}') == 1;
    final accepted = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => AlertDialog(
          title: Text(c == null ? 'Add category' : 'Edit category'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(controller: name, decoration: field('Name')),
                const SizedBox(height: 12),
                TextField(controller: slug, decoration: field('URL slug')),
                const SizedBox(height: 12),
                TextField(
                  controller: aliases,
                  decoration: field('Aliases, comma-separated'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: order,
                  decoration: field('Display order'),
                  keyboardType: TextInputType.number,
                ),
                SwitchListTile(
                  title: const Text('Active'),
                  value: active,
                  onChanged: (v) => set(() => active = v),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Save'),
            ),
          ],
        ),
      ),
    );
    if (accepted == true) {
      try {
        await auth.api.request(
          '/categories',
          data: {
            'id': c?['id'] ?? 0,
            'category_name': name.text,
            'category_slug': slug.text,
            'aliases': aliases.text,
            'display_order': int.tryParse(order.text) ?? 0,
            'active': active,
          },
        );
        await load();
      } catch (e) {
        if (mounted) showFailure(context, e);
      }
    }
    name.dispose();
    slug.dispose();
    aliases.dispose();
    order.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text(
        'Newsletter Manager',
        style: TextStyle(fontWeight: FontWeight.w800, fontSize: 19),
      ),
      leading: const Padding(
        padding: EdgeInsets.all(8),
        child: Brand(size: 38),
      ),
      actions: [IconButton(onPressed: load, icon: const Icon(Icons.refresh))],
    ),
    body: RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 30),
        children: [
          if (busy) const LinearProgressIndicator(),
          if (error != null) Notice(error!),
          if (tab == 0) dashboard(),
          if (tab == 1) ...[
            const Text(
              'Newsletter History',
              style: TextStyle(fontSize: 23, fontWeight: FontWeight.bold),
            ),
            ...history.map(newsletterTile),
            if (more)
              TextButton(onPressed: loadMore, child: const Text('Load more')),
          ],
          if (tab == 2) ...[
            const Text(
              'Categories',
              style: TextStyle(fontSize: 23, fontWeight: FontWeight.bold),
            ),
            const Text('Original section names, matched without AI.'),
            if (auth.user?['role'] == 'admin')
              TextButton.icon(
                onPressed: () => editCategory(),
                icon: const Icon(Icons.add),
                label: const Text('Add category'),
              ),
            ...categories.map(
              (c) => Card(
                child: ListTile(
                  leading: const Icon(Icons.folder_outlined, color: navy),
                  title: Text(c['category_name']),
                  subtitle: Text(
                    '${c['aliases']} · ${int.parse('${c['active']}') == 1 ? 'Active' : 'Inactive'}',
                  ),
                  onTap: auth.user?['role'] == 'admin'
                      ? () => editCategory(c)
                      : null,
                ),
              ),
            ),
          ],
          if (tab == 3) ...[
            const Brand(size: 90),
            Text(
              '${auth.user?['name']}',
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
            ),
            Text('${auth.user?['role']}'),
            const SizedBox(height: 18),
            const Text(
              'API server',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            SelectableText(auth.api.base),
            const SizedBox(height: 12),
            const Text(
              'Drafts are generated only when requested. Approval never sends an email campaign.',
            ),
            const SizedBox(height: 24),
            PrimaryButton(
              'Sign out',
              onPressed: () async {
                try {
                  await auth.logout();
                } catch (e) {
                  if (context.mounted) showFailure(context, e);
                }
              },
            ),
          ],
        ],
      ),
    ),
    bottomNavigationBar: NavigationBar(
      selectedIndex: tab,
      onDestinationSelected: (n) => setState(() => tab = n),
      destinations: const [
        NavigationDestination(
          icon: Icon(Icons.home_outlined),
          selectedIcon: Icon(Icons.home),
          label: 'Home',
        ),
        NavigationDestination(icon: Icon(Icons.history), label: 'History'),
        NavigationDestination(
          icon: Icon(Icons.folder_outlined),
          label: 'Categories',
        ),
        NavigationDestination(
          icon: Icon(Icons.settings_outlined),
          label: 'Settings',
        ),
      ],
    ),
  );
}
