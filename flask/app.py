#!/usr/bin/env python
# coding=utf-8
# libs
from flask import Flask
from flask import jsonify
import numpy as np
import schedule
import time
import matplotlib.pyplot as plt
import requests
import json
import pandas as pd
from datetime import date, datetime

# my-code
from recommendation_data import dataset
from collaborative_filtering import user_reommendations

app = Flask(__name__)
app.config['TESTING'] = True

global share_data

@app.route('/')
def hello_world():
    return "Hello World, Welcome Flask API"

@app.route('/api/caculator/recommend/CF_item_item/v1', methods=["GET", "POST"])
def recommend_CF_item_item():
    today = date.today()
    now = datetime.now()
    path_simmilarity = 'simmilarity/' + \
                       str(today.year) + '-' + \
                       str(today.month) + '-' + \
                       str(today.day) + 'T' + \
                       str(now.strftime("%H")) + '-' + \
                       str(now.strftime("%M")) + \
                       '_item_item_sim.csv'
    # get data from web-app server
    rate_json = requests.get('http://127.0.0.1/DATN-20182/public/api/data/rate/matrix/v1')
    like_json = requests.get('http://127.0.0.1/DATN-20182/public/api/data/like/matrix/v1')
    search_json = requests.get('http://127.0.0.1/DATN-20182/public/api/data/search/matrix/v1')
    watched_json = requests.get('http://127.0.0.1/DATN-20182/public/api/data/implict/matrix/v1')

    # build matrix user-item behavior
    rate_matrix = pd.read_json(rate_json.text)
    like_matrix = pd.read_json(like_json.text)
    search_matrix = pd.read_json(search_json.text)
    watched_matrix = pd.read_json(watched_json.text)

    # Caculator simmilarity of item-item for pearson
    rate_item_simmilarity = rate_matrix.corr('pearson', 1).replace(to_replace=float('nan'), value=0)
    search_item_simmilarity = search_matrix.corr('pearson', 1).replace(to_replace=float('nan'), value=0)
    watched_item_simmilarity = watched_matrix.corr('pearson', 1).replace(to_replace=float('nan'), value=0)

    # Integrate matrixs item-item simmilarity
    final_iteim_similarity = 1 / 7 * (4 * rate_item_simmilarity + watched_item_simmilarity + 2 * search_item_simmilarity)

    number_column = len(final_iteim_similarity.index)
    i = 0
    response_data = {}
    for col in final_iteim_similarity:
        i+=1
        _item = col
        _item_simmilarity = final_iteim_similarity[col].sort_values()
        k_NN_items = _item_simmilarity.loc[_item_simmilarity > 0]
        response_data[str(col)] = str(k_NN_items.index.tolist())
        # response_data[str(col)] = k_NN_items.index.tolist()
    # Send data recommended to Web-app
    # headers = {'content-type': 'application/json'}
    # res_handler = requests.post('http://127.0.0.1/DATN-20182/public/api/handler/recommended/result/v1', data=json.dumps(response_data), headers=headers)
    # print(res_handler.text)

    # save matrix simmilarity to file .csv
    final_iteim_similarity.to_csv(path_simmilarity, sep=',', encoding='utf-8')
    # return "Finish Caculator Recommendation"
    global share_data
    share_data = response_data
    with open('api/data.json', 'w') as json_file:
        json.dump(json.dumps(response_data), json_file)
    return json.dumps(response_data)

@app.route('/api/caculator/recommend/CF_user_user/v1')
def recommend_CF_user_user():
    return "Success"

@app.route('/api/response/data/recommended/post',  methods=["POST"])
def post_data_recommended():
    items_recommended = None
    with open('api/data.json') as json_file:
        data = json.load(json_file)
        items_recommended = data
    # print(items_recommended)
    # return json.dumps(items_recommended)
    global share_data
    return json.dumps(share_data)
    # return items_recommended

@app.route('/api/response/data/recommended')
def get_data_api():
    # result = {}
    # for predict in dataset:
    #     result[predict] = user_reommendations(predict)
    # print(result)
    return "SuccessFully"

if __name__ == '__main__':
    app.run()
