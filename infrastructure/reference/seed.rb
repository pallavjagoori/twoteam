# frozen_string_literal: true

fixture_time = Time.utc(2026, 1, 1, 0, 0, 0)

ActiveRecord::Base.transaction do
  account = Account.create!(
    id: 10_001,
    name: 'Twoteam Reference',
    status: 'active',
    created_at: fixture_time,
    updated_at: fixture_time
  )

  user = User.new(
    id: 10_001,
    provider: 'email',
    uid: 'agent@twoteam.test',
    name: 'Reference Agent',
    display_name: 'Reference Agent',
    email: 'agent@twoteam.test',
    password: 'Reference1!',
    type: 'SuperAdmin',
    created_at: fixture_time,
    updated_at: fixture_time
  )
  user.skip_confirmation!
  user.save!

  AccountUser.create!(
    account: account,
    user: user,
    role: :administrator,
    created_at: fixture_time,
    updated_at: fixture_time
  )

  channel = Channel::WebWidget.create!(
    id: 10_001,
    account: account,
    website_url: 'https://reference.twoteam.test',
    website_token: 'twoteam-reference-website-token',
    widget_color: '#1f93ff',
    created_at: fixture_time,
    updated_at: fixture_time
  )

  inbox = Inbox.create!(
    id: 10_001,
    account: account,
    channel: channel,
    name: 'Reference Inbox',
    created_at: fixture_time,
    updated_at: fixture_time
  )
  InboxMember.create!(user: user, inbox: inbox)

  contact = Contact.create!(
    id: 10_001,
    account: account,
    name: 'Reference Contact',
    email: 'contact@twoteam.test',
    created_at: fixture_time,
    updated_at: fixture_time
  )
  contact_inbox = ContactInbox.create!(
    id: 10_001,
    contact: contact,
    inbox: inbox,
    source_id: 'twoteam-reference-contact',
    pubsub_token: 'twoteam-reference-pubsub-token',
    created_at: fixture_time,
    updated_at: fixture_time
  )

  conversation = Conversation.create!(
    id: 10_001,
    account: account,
    inbox: inbox,
    contact: contact,
    contact_inbox: contact_inbox,
    assignee: user,
    identifier: 'twoteam-reference-conversation',
    status: :open,
    created_at: fixture_time,
    updated_at: fixture_time
  )

  Message.create!(
    id: 10_001,
    account: account,
    inbox: inbox,
    conversation: conversation,
    sender: contact,
    content: 'Reference incoming message',
    message_type: :incoming,
    content_type: :text,
    status: :sent,
    created_at: fixture_time,
    updated_at: fixture_time
  )
end

load '/reference/verify.rb'
