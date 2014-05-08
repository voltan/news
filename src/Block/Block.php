<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

/**
 * @author Hossein Azizabadi <azizabadi@faragostaresh.com>
 */
namespace Module\News\Block;

use Pi;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Json\Json;

class Block
{
    public static function item($options = array(), $module = null)
    {
        // Set options
        $block = array();
        $block = array_merge($block, $options);
        // Check topic permission
        if (!in_array(0, $block['topicid'])) {
            $whereLink['topic'] = $block['topicid'];
        }
        // Set model and get information
        $whereLink['status'] = 1;
        $columns = array('story' => new Expression('DISTINCT story'));
        if ($block['order'] == 'random') {
            $order = array(new \Zend\Db\Sql\Predicate\Expression('RAND()'));
        } else {
            $order = array('time_publish DESC', 'id DESC');
        }
        $limit = intval($block['number']);
        // Get info from link table
        $select = Pi::model('link', $module)->select()->where($whereLink)->columns($columns)->order($order)->limit($limit);
        $rowset = Pi::model('link', $module)->selectWith($select)->toArray();
        // Make list
        foreach ($rowset as $id) {
            $storyId[] = $id['story'];
        }
        // Set info
        $whereStory = array('status' => 1, 'id' => $storyId);
        // Get topic list
        $topicList = Pi::api('topic', 'news')->topicList();
        // Get list of story
        $select = Pi::model('story', $module)->select()->where($whereStory)->order($order);
        $rowset = Pi::model('story', $module)->selectWith($select);
        // Make list
        foreach ($rowset as $row) {
            $story[$row->id] = Pi::api('story', 'news')->canonizeStory($row, $topicList);
        }
        // Set block array
        $block['resources'] = $story;
        return $block;
    }

    public static function spotlight($options = array(), $module = null)
    {
        // Set options
        $block = array();
        $block = array_merge($block, $options);
        // Get config
        $config = Pi::service('registry')->config->read($module);
        // Ckeck block ids
        if (empty($block['topicid'])) {
            return $block;
        }
        // Make Block lope
        foreach ($block['topicid'] as $topicId) {
            $topic = Pi::model('topic', $module)->find($topicId)->toArray();
            // Set topic url
            $topic['topicUrl'] = Pi::service('url')->assemble('news', array(
                'module'        => $module,
                'controller'    => 'topic',
                'slug'          => $topic['slug'],
            ));
            // Set image url
            if ($topic['image']) {
                // Set image medium url
                $topic['mediumUrl'] = Pi::url(
                    sprintf('upload/%s/medium/%s/%s', 
                        $config['image_path'], 
                        $topic['path'], 
                        $topic['image']
                    ));
                // Set image thumb url
                $topic['thumbUrl'] = Pi::url(
                    sprintf('upload/%s/thumb/%s/%s', 
                        $config['image_path'], 
                        $topic['path'], 
                        $topic['image']
                    ));
            }
            // Set link model and get information
            $whereLink = array('status' => 1, 'topic' => $topic['id']);
            $columns = array('story' => new Expression('DISTINCT story'));
            if ($block['order'] == 'random') {
                $order = array(new \Zend\Db\Sql\Predicate\Expression('RAND()'));
            } else {
                $order = array('time_publish DESC', 'id DESC');
            }
            $limit = intval($block['number']);
            // Get info from link table
            $select = Pi::model('link', $module)->select()->where($whereLink)->columns($columns)->order($order)->limit($limit);
            $rowset = Pi::model('link', $module)->selectWith($select)->toArray();
            // Make list
            foreach ($rowset as $id) {
                $storyId[] = $id['story'];
            }
            // Set info
            $whereStory = array('status' => 1, 'id' => $storyId);
            // Get list of story
            $select = Pi::model('story', $module)->select()->where($whereStory);
            $rowset = Pi::model('story', $module)->selectWith($select);
            // Make list
            $story = array();
            foreach ($rowset as $row) {
                $story[$row->id] = Pi::api('story', 'news')->canonizeStoryLight($row);
            }
            //
            $spotlight[$topicId]['topic'] = $topic;
            $spotlight[$topicId]['story'] = $story;
            unset($topic);
            unset($story);
            unset($storyId);
        }
        // Set block array
        $block['resources'] = $spotlight;
        return $block;
    }

    public static function topic($options = array(), $module = null)
    {
        // Set options
        $block = array();
        $block = array_merge($block, $options);
        // Set model and get information
        $columns = array('id', 'title', 'slug');
        $where = array('status' => 1);
        if (!in_array(0, $block['topicid'])) {
            $where['id'] = $block['topicid'];
        }
        $order = array('time_create DESC', 'id DESC');
        $select = Pi::model('topic', $module)->select()->columns($columns)->where($where)->order($order);
        $rowset = Pi::model('topic', $module)->selectWith($select);
        // Process information
        foreach ($rowset as $row) {
            $topic[$row->id] = $row->toArray();
            $topic[$row->id]['topicUrl'] = Pi::service('url')->assemble('news', array(
                'module'        => $module,
                'controller'    => 'topic',
                'slug'          => $topic[$row->id]['slug'],
            ));
        }
        // Set block array
        $block['resources'] = $topic;
        return $block;
    }

    public static function writer($options = array(), $module = null)
    {
        // Set options
        $block = array();
        $block = array_merge($block, $options);
        // Set model and get information
        $select = Pi::model('writer', $module)->select()->order(array('count DESC'));
        $rowset = Pi::model('writer', $module)->selectWith($select);
        // Make list
        foreach ($rowset as $row) {
            $list[$row->id] = $row->toArray();
            $user = Pi::user()->get($row->uid, array('id', 'identity', 'name', 'email'));
            $user['avatar'] = Pi::service('user')->avatar($list[$row->id]['uid'], 'medium');
            $list[$row->id] = array_merge($user, $list[$row->id]);
            $list[$row->id]['url'] = Pi::service('url')->assemble('news', array(
                'module'      => $module,
                'controller'  => 'writer',
                'id'          => $row->uid,
            ));
        }
        // Set block array
        $block['resources'] = $list;
        $block['linkblockmore'] = Pi::service('url')->assemble('news', array(
                'module'      => $module,
                'controller'  => 'writer',
                'action'      => 'list',
        ));
        return $block;
    }

    public static function gallery($options = array(), $module = null)
    {
        // Set options
        $block = array();
        $block = array_merge($block, $options);
        // Get config
        $config = Pi::service('registry')->config->read($module);
        // Set info
        $where = array('type' => 'image');
        $order = array('time_create DESC', 'id DESC');
        $limit = intval($block['number']);
        // Select
        $select = Pi::model('attach', $module)->select()->where($where)->order($order)->limit($limit);
        $rowset = Pi::model('attach', $module)->selectWith($select);
        // Make list
        foreach ($rowset as $row) {
            $list[$row->id] = $row->toArray();
            // Set image links
            $list[$row->id]['largeUrl'] = Pi::url(
                sprintf('upload/%s/large/%s/%s', 
                    $config['image_path'], 
                    $list[$row->id]['path'], 
                    $list[$row->id]['file']
            )); 
            $list[$row->id]['mediumUrl'] = Pi::url(
                sprintf('upload/%s/medium/%s/%s', 
                    $config['image_path'], 
                    $list[$row->id]['path'], 
                    $list[$row->id]['file']
            )); 
            $list[$row->id]['thumbUrl'] = Pi::url(
                sprintf('upload/%s/thumb/%s/%s', 
                    $config['image_path'], 
                    $list[$row->id]['path'], 
                    $list[$row->id]['file']
            ));
            // Set mediaUrl
            $list[$row->id]['mediaUrl'] = Pi::service('url')->assemble('news', array(
                'module'      => $module,
                'controller'  => 'media',
                'action'      => 'explorer',
                'id'          => $list[$row->id]['id'],
            ));
        }
        // Set block array
        $block['resources'] = $list;
        return $block;
    }
}