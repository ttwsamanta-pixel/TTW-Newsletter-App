import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

class ApiException implements Exception {
  final String message;
  final int status;
  final int? existingId;
  ApiException(this.message, [this.status = 0, this.existingId]);
  @override
  String toString() => message;
}

class Api {
  static const baseUrl = String.fromEnvironment(
    'API_URL',
    defaultValue: 'https://newsletter.travelandtourworld.com/api/index.php',
  );
  final http.Client client;
  final String base;
  String? token;
  VoidCallback? onExpired;
  Api({http.Client? client, this.base = baseUrl})
    : client = client ?? http.Client() {
    if (Uri.parse(base).scheme != 'https') {
      throw ArgumentError('API_URL must use HTTPS.');
    }
  }
  Uri uri(String route, [Map<String, String> query = const {}]) =>
      Uri.parse(base).replace(queryParameters: {'route': route, ...query});
  Map<String, String> get headers => {
    'Accept': 'application/json',
    if (token != null) 'Authorization': 'Bearer $token',
  };
  Future<Map<String, dynamic>> request(
    String route, {
    Map<String, dynamic>? data,
    Map<String, String> query = const {},
  }) async {
    try {
      final response =
          await (data == null
                  ? client.get(uri(route, query), headers: headers)
                  : client.post(
                      uri(route),
                      headers: {...headers, 'Content-Type': 'application/json'},
                      body: jsonEncode(data),
                    ))
              .timeout(const Duration(seconds: 30));
      return decode(response.statusCode, response.body);
    } on TimeoutException {
      throw ApiException('The server took too long. Try again.');
    } on SocketException {
      throw ApiException('No connection. Check your network and try again.');
    } on http.ClientException {
      throw ApiException('Cannot reach the server. Check your connection.');
    }
  }

  Map<String, dynamic> decode(int status, String body) {
    Map<String, dynamic> result;
    try {
      result = jsonDecode(body) as Map<String, dynamic>;
    } catch (_) {
      throw ApiException('The server returned an unexpected response.', status);
    }
    if (status >= 400) {
      if (status == 401 && token != null) onExpired?.call();
      throw ApiException(
        result['error']?.toString() ?? 'Request failed.',
        status,
        (result['existing_id'] as num?)?.toInt(),
      );
    }
    return result;
  }

  Future<Map<String, dynamic>> upload(String path) async {
    try {
      final request = http.MultipartRequest('POST', uri('/uploads/promotion'));
      request.headers.addAll(headers);
      request.files.add(await http.MultipartFile.fromPath('banner', path));
      final response = await client
          .send(request)
          .timeout(const Duration(seconds: 45));
      return decode(response.statusCode, await response.stream.bytesToString());
    } on TimeoutException {
      throw ApiException('Upload timed out. Try again.');
    } on IOException {
      throw ApiException('Could not read or upload the image.');
    } on http.ClientException {
      throw ApiException('Upload failed. Check your connection.');
    }
  }
}
