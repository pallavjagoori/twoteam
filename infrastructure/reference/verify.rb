# frozen_string_literal: true

require 'digest'
require 'json'

fixture = {
  account: Account.find(10_001).slice('id', 'name', 'status'),
  user: User.find(10_001).slice('id', 'name', 'email'),
  inbox: Inbox.find(10_001).slice('id', 'name'),
  contact: Contact.find(10_001).slice('id', 'name', 'email'),
  conversation: Conversation.find(10_001).slice('id', 'status', 'identifier'),
  message: Message.find(10_001).slice('id', 'content', 'message_type', 'content_type', 'status')
}
canonical_fixture = JSON.generate(fixture)
expected_digest = '768c18217471fee56871f0ea2c9fa848901537e27180a2ccc217b33705c13847'
actual_digest = Digest::SHA256.hexdigest(canonical_fixture)

raise "Reference fixture digest mismatch: #{actual_digest}" unless actual_digest == expected_digest

puts "Reference fixture verified: #{actual_digest}"
