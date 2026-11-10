import 'package:flutter/material.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';
import '../../widgets/manager_ui.dart';
import 'details.dart';

class LibraryScreen extends StatefulWidget {
  final Auth auth;
  final bool history;
  final String initialStatus;
  const LibraryScreen({super.key, required this.auth, this.history = false,this.initialStatus=''});
  @override
  State<LibraryScreen> createState() => _LibraryState();
}

class _LibraryState extends State<LibraryScreen> {
  final search = TextEditingController();
  String edition = '', status = '', from = '', to = '';
  List<Map<String, dynamic>> rows = [];
  bool busy = true, more = false;
  int pageNo = 1, total = 0;
  String? error;
  @override
  void initState() {
    super.initState();
    status = widget.initialStatus;
    load();
  }

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  Future<void> load({bool append = false}) async {
    if (!mounted) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final r = await widget.auth.api.request(
        '/newsletters',
        query: {
          'page': '${append ? pageNo + 1 : 1}',
          if (search.text.isNotEmpty) 'q': search.text,
          if (edition.isNotEmpty) 'edition': edition,
          if (status.isNotEmpty) 'status': status,
          if (from.isNotEmpty) 'from': from,
          if (to.isNotEmpty) 'to': to,
        },
      );
      if (mounted) {
        setState(() {
          final next = (r['newsletters'] as List).cast<Map<String, dynamic>>();
          rows = append ? [...rows, ...next] : next;
          pageNo = append ? pageNo + 1 : 1;
          more = r['has_more'] == true;
          total = number(r['total']);
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> filters() async {
    var e = edition, s = status, f = from, t = to;
    final apply = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Filter Newsletters',
                  style: TextStyle(fontSize: 21, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 20),
                OutlinedButton.icon(
                  icon: const Icon(Icons.date_range),
                  label: Text(f.isEmpty ? 'Choose date range' : '$f – $t'),
                  onPressed: () async {
                    final range = await showDateRangePicker(
                      context: ctx,
                      firstDate: DateTime(2000),
                      lastDate: DateTime(2199),
                    );
                    if (range != null) {
                      set(() {
                        f = range.start.toIso8601String().substring(0, 10);
                        t = range.end.toIso8601String().substring(0, 10);
                      });
                    }
                  },
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: e,
                  decoration: field('Edition'),
                  items: const [
                    DropdownMenuItem(value: '', child: Text('All editions')),
                    DropdownMenuItem(value: 'M', child: Text('Morning')),
                    DropdownMenuItem(value: 'E', child: Text('Evening')),
                  ],
                  onChanged: (v) => set(() => e = v!),
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: s,
                  decoration: field('Status'),
                  items: [
                    for (final v in ['', 'draft', 'approved', 'published'])
                      DropdownMenuItem(
                        value: v,
                        child: Text(
                          v.isEmpty
                              ? 'All statuses'
                              : v[0].toUpperCase() + v.substring(1),
                        ),
                      ),
                  ],
                  onChanged: (v) => set(() => s = v!),
                ),
                const SizedBox(height: 24),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () {
                          set(() {
                            e = '';
                            s = '';
                            f = '';
                            t = '';
                          });
                        },
                        child: const Text('Reset'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: FilledButton(
                        onPressed: () => Navigator.pop(ctx, true),
                        child: const Text('Apply'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
    if (apply == true) {
      edition = e;
      status = s;
      from = f;
      to = t;
      await load();
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(widget.history ? 'Newsletter History' : 'Newsletters'),
      actions: [
        IconButton(
          tooltip: 'Filter newsletters',
          onPressed: filters,
          icon: const Icon(Icons.filter_list),
        ),
      ],
    ),
    body: SafeArea(
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: search,
              decoration: field('Search newsletters...', icon: Icons.search)
                  .copyWith(
                    suffixIcon: IconButton(
                      onPressed: load,
                      icon: const Icon(Icons.arrow_forward),
                    ),
                  ),
              onSubmitted: (_) => load(),
            ),
          ),
          if (busy) const LinearProgressIndicator(),
          if (error != null) Notice(error!),
          Expanded(
            child: RefreshIndicator(
              onRefresh: load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(horizontal: 16),
                children: [
                  if (!busy && rows.isEmpty)
                    const EmptyState(
                      'No Data Found',
                      'No newsletters match these filters.',
                    ),
                  if (rows.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: Text(
                        '$total newsletters',
                        style: const TextStyle(color: navy),
                      ),
                    ),
                  ...rows.map(
                    (n) => NewsletterCard(
                      auth: widget.auth,
                      n: n,
                      onTap: () async {
                        await openPage(
                          context,
                          NewsletterDetailsScreen(
                            auth: widget.auth,
                            id: number(n['id']),
                          ),
                        );
                        if (mounted) load();
                      },
                    ),
                  ),
                  if (more)
                    TextButton(
                      onPressed: busy ? null : () => load(append: true),
                      child: const Text('Load More'),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
