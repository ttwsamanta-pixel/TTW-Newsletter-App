import 'package:flutter/material.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';
import '../../widgets/manager_ui.dart';
import '../create_newsletter/create.dart';
import '../create_newsletter/sections.dart';
import '../newsletters/library.dart';
import '../newsletters/details.dart';
import '../reports/reports.dart';
import '../settings/settings.dart';
import '../notifications/notifications.dart';
import '../automation/monitor.dart';

class HomeScreen extends StatefulWidget {
  final Auth auth;
  const HomeScreen({super.key, required this.auth});
  @override
  State<HomeScreen> createState() => _HomeState();
}

class _HomeState extends State<HomeScreen> {
  int tab = 0;
  String libraryStatus = '';
  Map<String, dynamic>? data;
  String? error;
  bool busy = true;
  Auth get auth => widget.auth;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (!auth.can('view')) {
      setState(() => busy = false);
      return;
    }
    setState(() => error = null);
    try {
      final r = await auth.api.request('/dashboard');
      if (mounted) setState(() => data = r);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> create() async {
    await openPage(context, CreateModeScreen(auth: auth));
    if (mounted) load();
  }

  Widget metric(String label, dynamic value, List<Color> colors) => Expanded(
    child: Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: LinearGradient(colors: colors),
        boxShadow: [
          BoxShadow(
            color: colors.first.withValues(alpha: .15),
            blurRadius: 12,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '$value',
            style: const TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w800,
              color: Colors.white,
            ),
          ),
          Text(
            label,
            style: const TextStyle(color: Colors.white, fontSize: 13),
          ),
        ],
      ),
    ),
  );
  Widget dashboard() {
    final stats = data?['stats'] as Map? ?? {};
    final today = data?['today'] as List? ?? [];
    return RefreshIndicator(
      onRefresh: load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          Text(
            'Good ${DateTime.now().hour < 12 ? 'Morning' : 'Afternoon'},',
            style: const TextStyle(
              color: navy,
              fontSize: 20,
              fontWeight: FontWeight.w600,
            ),
          ),
          Text(
            auth.user?['name'] ?? 'TTW Team',
            style: const TextStyle(
              color: navy,
              fontSize: 27,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 7),
          const Text(
            'Your daily travel news, ready for the world.',
            style: TextStyle(color: Colors.blueGrey),
          ),
          const SizedBox(height: 24),
          if (busy) const LinearProgressIndicator(),
          if (error != null)
            EmptyState('Unable to load dashboard', error!, retry: load),
          if (data != null) ...[
            Row(
              children: [
                metric('Total', stats['total'], [
                  const Color(0xff16b6dc),
                  blue,
                ]),
                const SizedBox(width: 12),
                metric('Morning', stats['morning'], [
                  const Color(0xff60cc51),
                  const Color(0xff159f4a),
                ]),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                metric('Evening', stats['evening'], [
                  const Color(0xffffb827),
                  const Color(0xffff751c),
                ]),
                const SizedBox(width: 12),
                metric('This Month', stats['this_month'], [
                  const Color(0xff9b64ff),
                  const Color(0xff6742df),
                ]),
              ],
            ),
            const SizedBox(height: 25),
            const Text(
              "Today's Newsletter",
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 10),
            Card(
              child: Column(
                children: [
                  for (final e in ['M', 'E'])
                    Builder(
                      builder: (ctx) {
                        final matches = today.where((n) => n['edition'] == e);
                        final n = matches.isEmpty ? null : matches.first;
                        return ListTile(
                          leading: Icon(
                            e == 'M'
                                ? Icons.wb_sunny_outlined
                                : Icons.nightlight_outlined,
                            color: e == 'M' ? Colors.orange : blue,
                          ),
                          title: Text(e == 'M' ? 'Morning' : 'Evening'),
                          trailing: StatusBadge(
                            n == null
                                ? 'Not Created'
                                : n['approved_at'] == null
                                ? 'Draft'
                                : 'Approved',
                          ),
                          onTap: n == null
                              ? auth.can('create')
                                    ? create
                                    : null
                              : () => openPage(
                                  context,
                                  NewsletterDetailsScreen(
                                    auth: auth,
                                    id: number(n['id']),
                                  ),
                                ),
                        );
                      },
                    ),
                ],
              ),
            ),
            const SizedBox(height: 18),
          ],
          if (auth.can('create'))
            PrimaryButton('+ Create Newsletter', onPressed: create),
          const SizedBox(height: 20),
          Wrap(
            spacing: 12,
            runSpacing: 10,
            children: [
              if (auth.can('create'))
                ActionChip(
                  avatar: const Icon(Icons.auto_mode, size: 18),
                  label: const Text('Automation'),
                  onPressed: () =>
                      openPage(context, AutomationScreen(auth: auth)),
                ),
              if (auth.can('view'))
                ActionChip(
                  avatar: const Icon(Icons.edit_note, size: 18),
                  label: Text('${stats['drafts'] ?? 0} Drafts'),
                  onPressed: () => setState(() {
                    libraryStatus = 'draft';
                    tab = 1;
                  }),
                ),
            ],
          ),
          const SizedBox(height: 20),
          if (data != null) ...[
            const Text(
              'Recent Newsletters',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            if ((data!['recent'] as List).isEmpty)
              const EmptyState(
                'No Data Found',
                'Create your first newsletter.',
              ),
            for (final n in (data!['recent'] as List).take(5))
              NewsletterCard(
                auth: auth,
                n: n,
                onTap: () async {
                  await openPage(
                    context,
                    NewsletterDetailsScreen(auth: auth, id: number(n['id'])),
                  );
                  if (mounted) load();
                },
              ),
          ],
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: tab == 0
        ? AppBar(
            leading: IconButton(
              tooltip: 'More',
              onPressed: () => setState(() => tab = 4),
              icon: const Icon(Icons.menu),
            ),
            title: const Brand(size: 52),
            actions: [
              IconButton(
                tooltip: 'Notifications',
                onPressed: () =>
                    openPage(context, NotificationsScreen(auth: auth)),
                icon: const Icon(Icons.notifications_outlined),
              ),
            ],
          )
        : null,
    body: tab == 0
        ? dashboard()
        : tab == 1
        ? auth.can('view')
              ? LibraryScreen(
                  key: ValueKey(libraryStatus),
                  auth: auth,
                  initialStatus: libraryStatus,
                )
              : const EmptyState(
                  'Permission required',
                  'Your account cannot view newsletters.',
                )
        : tab == 3
        ? auth.can('view')
              ? ReportsScreen(auth: auth)
              : const EmptyState(
                  'Permission required',
                  'Your account cannot view reports.',
                )
        : SettingsScreen(auth: auth),
    bottomNavigationBar: NavigationBar(
      selectedIndex: tab,
      onDestinationSelected: (i) {
        if (i == 2) {
          if (auth.can('create')) create();
          return;
        }
        setState(() {
          tab = i;
          if (i == 1) libraryStatus = '';
        });
      },
      destinations: const [
        NavigationDestination(
          icon: Icon(Icons.home_outlined),
          selectedIcon: Icon(Icons.home),
          label: 'Home',
        ),
        NavigationDestination(
          icon: Icon(Icons.newspaper_outlined),
          label: 'Newsletters',
        ),
        NavigationDestination(
          icon: CircleAvatar(
            backgroundColor: blue,
            radius: 19,
            child: Icon(Icons.add, color: Colors.white),
          ),
          label: 'Create',
        ),
        NavigationDestination(
          icon: Icon(Icons.bar_chart_outlined),
          label: 'Reports',
        ),
        NavigationDestination(icon: Icon(Icons.more_horiz), label: 'More'),
      ],
    ),
  );
}

class CreateModeScreen extends StatelessWidget {
  final Auth auth;
  const CreateModeScreen({super.key, required this.auth});
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Create Newsletter')),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          const Text(
            'How would you like to create?',
            style: TextStyle(
              fontWeight: FontWeight.w800,
              fontSize: 24,
              color: navy,
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            'Use one batch of links, or save each section as the news arrives.',
          ),
          const SizedBox(height: 30),
          _mode(
            context,
            'All Links at Once',
            'Paste all section links in one go and import.',
            Icons.copy_all_outlined,
            CreateScreen(auth: auth),
          ),
          const SizedBox(height: 18),
          _mode(
            context,
            'Section by Section',
            'Basic, Header, Pick News, More News, Categories and Promotion. Save and resume in any order.',
            Icons.view_list_outlined,
            SectionDraftScreen(auth: auth),
          ),
        ],
      ),
    ),
  );
  Widget _mode(
    BuildContext context,
    String title,
    String subtitle,
    IconData icon,
    Widget screen,
  ) => Card(
    child: InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: () => openPage(context, screen),
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Row(
          children: [
            CircleAvatar(
              backgroundColor: blue.withValues(alpha: .1),
              child: Icon(icon, color: blue),
            ),
            const SizedBox(width: 17),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 19,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(subtitle),
                ],
              ),
            ),
            const Icon(Icons.chevron_right),
          ],
        ),
      ),
    ),
  );
}
