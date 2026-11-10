import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:ttw_newsletter/core/api.dart';
import 'package:ttw_newsletter/core/auth.dart';
import 'package:ttw_newsletter/features/dashboard/home.dart';
import 'package:ttw_newsletter/features/newsletters/library.dart';
import 'package:ttw_newsletter/features/newsletters/details.dart';
import 'package:ttw_newsletter/features/notifications/notifications.dart';
import 'package:ttw_newsletter/features/admin/masters.dart';
import 'package:ttw_newsletter/features/create_newsletter/article_editor.dart';

Auth fixture(Future<http.Response> Function(http.Request) handler) =>
    Auth(Api(client: MockClient(handler)))
      ..user = {
        'name': 'TTW Admin',
        'role': 'admin',
        'permissions': ['view', 'create', 'edit', 'delete', 'download'],
      };
http.Response json(Map<String, dynamic> data) =>
    http.Response(jsonEncode(data), 200);
Map<String, dynamic> newsletter() => {
  'id': 7,
  'newsletter_date': '2026-11-10',
  'edition': 'M',
  'subject_title': 'Original travel headline',
  'header_title': 'Original Header',
  'header_description': 'Body text...',
  'header_image': null,
  'approved_at': '2026-11-10 10:00:00',
  'pick_news': [],
  'more_news': [],
  'categories': [],
  'publication': {'status': 'unpublished'},
};
Future<void> display(WidgetTester tester, Widget screen) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(MaterialApp(home: screen));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('Dashboard uses server counts and provides both creation modes', (
    t,
  ) async {
    final auth = fixture(
      (r) async => json({
        'stats': {
          'total': 135,
          'morning': 68,
          'evening': 67,
          'this_month': 19,
          'drafts': 2,
        },
        'today': [],
        'recent': [],
      }),
    );
    await display(t, HomeScreen(auth: auth));
    expect(find.text('135'), findsOneWidget);
    expect(find.text('68'), findsOneWidget);
    expect(find.text('19'), findsOneWidget);
    await t.tap(find.text('+ Create Newsletter'));
    await t.pumpAndSettle();
    expect(find.text('All Links at Once'), findsOneWidget);
    expect(find.text('Section by Section'), findsOneWidget);
    expect(t.takeException(), isNull);
  });
  testWidgets('Library sends combined search and applied edition filters', (
    t,
  ) async {
    Map<String, String> query = {};
    final auth = fixture((r) async {
      query = r.url.queryParameters;
      return json({
        'newsletters': [newsletter()],
        'total': 1,
        'has_more': false,
      });
    });
    await display(t, LibraryScreen(auth: auth));
    await t.enterText(find.byType(TextField), 'travel');
    await t.tap(find.byIcon(Icons.arrow_forward));
    await t.pumpAndSettle();
    expect(query['q'], 'travel');
    await t.tap(find.byTooltip('Filter newsletters'));
    await t.pumpAndSettle();
    await t.tap(find.text('All editions'));
    await t.pumpAndSettle();
    await t.tap(find.text('Morning').last);
    await t.pumpAndSettle();
    await t.tap(find.text('Apply'));
    await t.pumpAndSettle();
    expect(query['edition'], 'M');
    expect(find.text('Original travel headline'), findsOneWidget);
    expect(t.takeException(), isNull);
  });
  testWidgets('Publication requires confirmation and uses backend endpoint', (
    t,
  ) async {
    bool published = false;
    final auth = fixture((r) async {
      final route = r.url.queryParameters['route'];
      if (route!.endsWith('/publish-html')) {
        published = true;
        expect(jsonDecode(r.body)['replace'], false);
        return json({
          'publication': {
            'status': 'published',
            'public_url': 'https://www.travelandtourworld.com/newsletter/2026/Nov/TTW_newsletter_10Nov2026M.html',
          },
        });
      }
      return json({'newsletter': newsletter()});
    });
    await display(t, NewsletterDetailsScreen(auth: auth, id: 7));
    await t.scrollUntilVisible(
      find.text('Publish HTML'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await t.tap(find.text('Publish HTML'));
    await t.pumpAndSettle();
    expect(published, false);
    expect(find.text('Publish HTML?'), findsOneWidget);
    await t.tap(find.text('Continue'));
    await t.pumpAndSettle();
    expect(published, true);
    await t.scrollUntilVisible(
      find.text('Copy URL'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('View Published HTML'), findsOneWidget);
    expect(t.takeException(), isNull);
  });
  testWidgets('Employee permission changes submit actual toggles', (t) async {
    Map? saved;
    final auth = fixture((r) async {
      saved = jsonDecode(r.body);
      return json({'id': 4});
    });
    await display(
      t,
      EmployeeEditScreen(
        auth: auth,
        employee: {
          'id': 4,
          'username': 'employee',
          'first_name': 'Employee',
          'last_name': 'Test',
          'email': 'employee@example.invalid',
          'status': 'active',
          'permissions': ['view'],
        },
      ),
    );
    await t.scrollUntilVisible(
      find.text('Delete newsletters'),
      250,
      scrollable: find.byType(Scrollable).first,
    );
    await t.tap(find.text('Delete newsletters'));
    await t.scrollUntilVisible(
      find.text('Save Employee & Permissions'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await t.tap(find.text('Save Employee & Permissions'));
    await t.pumpAndSettle();
    expect(saved!['permissions'], contains('delete'));
    expect(saved!['permissions'], contains('view'));
  });
  testWidgets('Notifications persist read acknowledgement', (t) async {
    var read = false;
    final auth = fixture((r) async {
      if (r.url.queryParameters['route'] == '/notifications/read') {
        read = true;
        return json({'ok': true});
      }
      return json({
        'unread': read ? 0 : 1,
        'notifications': [
          {
            'key': 'event-1',
            'title': 'Newsletter Approved',
            'message': 'Morning edition',
            'time': '2026-11-10 09:00',
            'kind': 'success',
            'read': read,
          },
        ],
      });
    });
    await display(t, NotificationsScreen(auth: auth));
    expect(find.text('Unread (1)'), findsOneWidget);
    await t.tap(find.text('Mark all as read'));
    await t.pumpAndSettle();
    expect(read, true);
    expect(find.text('Unread (0)'), findsOneWidget);
    expect(t.takeException(), isNull);
  });
  testWidgets('Article removal saves only the selected section', (t) async {
    Map? saved;
    final auth = fixture((r) async {
      if (r.method == 'POST') {
        saved = jsonDecode(r.body);
        return json({'newsletter': newsletter()});
      }
      final n = newsletter();
      n['pick_news'] = [
        {
          'id': 1,
          'title': 'First Pick',
          'description': 'First body',
          'url': 'https://example.com/1',
        },
        {
          'id': 2,
          'title': 'Second Pick',
          'description': 'Second body',
          'url': 'https://example.com/2',
        },
      ];
      return json({'newsletter': n});
    });
    await display(t, ArticleEditorScreen(auth: auth, id: 7, section: 'pick'));
    await t.tap(find.byTooltip('Remove article').first);
    await t.pumpAndSettle();
    await t.tap(find.text('Save Section'));
    await t.pumpAndSettle();
    expect(saved!['section'], 'pick');
    expect((saved!['items'] as List).single['id'], 2);
    expect(saved!.containsKey('more_news'), false);
  });
}
